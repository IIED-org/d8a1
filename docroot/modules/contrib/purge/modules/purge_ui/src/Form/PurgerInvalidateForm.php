<?php

declare(strict_types=1);

namespace Drupal\purge_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\InvalidExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\MissingExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvStatesInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\Exception\CapacityException;
use Drupal\purge\Plugin\Purge\Purger\Exception\DiagnosticsException;
use Drupal\purge\Plugin\Purge\Purger\Exception\LockException;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Directly invalidate items of a type supported by the {id} purger instance.
 *
 * Note that the purgers service always dispatches invalidations to every
 * enabled purger that supports the selected type, so the selected purger only
 * determines which types are offered. See ::getDescription().
 */
final class PurgerInvalidateForm extends ConfirmFormBase {
  use CloseDialogTrait;

  /**
   * Unique instance ID for the purger being invalidated.
   *
   * @var string
   */
  protected $id;

  /**
   * The plugin definition for the purger being invalidated.
   *
   * @var array
   */
  protected $definition;

  /**
   * The 'purge_ui_invalidate' processor plugin.
   *
   * @var false|\Drupal\purge\Plugin\Purge\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * The purge executive service, which wipes content from external caches.
   *
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * The service that instantiates invalidation objects on-demand.
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * Constructs a PurgerInvalidateForm object.
   *
   * @param \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface $purge_processors
   *   The purge processors service.
   * @param \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface $purge_purgers
   *   The purgers service.
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface $purge_invalidation_factory
   *   The purge invalidations factory service.
   */
  public function __construct(ProcessorsServiceInterface $purge_processors, PurgersServiceInterface $purge_purgers, InvalidationsServiceInterface $purge_invalidation_factory) {
    $this->processor = $purge_processors->get('purge_ui_invalidate');
    $this->purgePurgers = $purge_purgers;
    $this->purgeInvalidationFactory = $purge_invalidation_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('purge.processors'),
      $container->get('purge.purgers'),
      $container->get('purge.invalidation.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purge_ui.purger_invalidate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->id = $form_state->getBuildInfo()['args'][0]['id'];
    $this->definition = $form_state->getBuildInfo()['args'][0]['definition'];
    $form = parent::buildForm($form, $form_state);

    // This is rendered as a modal dialog, so we need to set some extras.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#prefix'] = '<div id="invalidate-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Determine the selected type, defaulting to 'everything' when supported.
    $types = $this->getPurgerTypes();
    $type = $form_state->getValue('type');
    if ($type === NULL || !isset($types[$type])) {
      $type = isset($types['everything']) ? 'everything' : key($types);
    }

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $types,
      '#default_value' => $type,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateForm',
        'wrapper' => 'invalidate-form-wrapper',
      ],
      '#limit_validation_errors' => [['type']],
    ];
    $form['expression'] = $this->buildExpression($type);

    // Update the buttons and bind callbacks.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->getConfirmText(),
      '#ajax' => [
        'callback' => '::invalidatePurger',
        'wrapper' => 'invalidate-form-wrapper',
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#weight' => -10,
      '#executes_submit_callback' => FALSE,
      '#ajax' => ['callback' => '::closeDialog'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = ['@label' => $this->purgePurgers->getLabels()[$this->id]];
    return $this->t('What do you want to invalidate with @label?', $label);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The selected purger determines which types are offered. Every enabled purger that supports the selected type will process the invalidation immediately, bypassing the queue.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Invalidate');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // Rendered as a modal dialog; cancel is handled via AJAX close button.
    return Url::fromRoute('purge_ui.dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (($trigger['#type'] ?? '') !== 'submit' || ($trigger['#button_type'] ?? '') !== 'primary') {
      return;
    }

    if (!$this->processor) {
      $form_state->setErrorByName('type', $this->t('Please enable the "Direct invalidate" processor first.'));
      return;
    }

    $type = $form_state->getValue('type');
    $typedef = $this->purgeInvalidationFactory->getPlugins()[$type];

    // Types without expressions are instantiated exactly once, with NULL.
    if (!$typedef['expression_required']) {
      $expressions = [NULL];
    }
    else {
      $expressions = preg_split('/\R/', (string) $form_state->getValue('expression'));
      $expressions = array_values(array_unique(array_filter(array_map('trim', $expressions), 'strlen')));
      if (!$expressions) {
        // Let the invalidation plugin explain the problem.
        $expressions = [NULL];
      }
    }

    // Instantiate the invalidation objects based on user input.
    $invalidations = [];
    foreach ($expressions as $expression) {
      try {
        $invalidations[] = $this->purgeInvalidationFactory->get($type, $expression);
      }
      catch (TypeUnsupportedException $e) {
        $form_state->setErrorByName('type', $this->t('No purger supports the type "@type" anymore.', ['@type' => $type]));
        break;
      }
      catch (InvalidExpressionException | MissingExpressionException $e) {
        $form_state->setErrorByName('expression', $e->getMessage());
      }
    }

    $form_state->set('invalidations', $invalidations);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Returns updated form in case of ajax requests.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Invalidate the requested items and close the dialog.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   The form when it has validation errors, or an AJAX response.
   */
  public function invalidatePurger(array &$form, FormStateInterface $form_state) {
    // Refresh the form if there are validation errors.
    if ($form_state->hasAnyErrors()) {
      return $this->updateForm($form, $form_state);
    }

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());

    if (isset($this->purgePurgers->getPluginsEnabled()[$this->id])) {
      /** @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface[] $invalidations */
      $invalidations = $form_state->get('invalidations') ?: [];

      // Attempt the cache invalidation and deal with errors.
      try {
        $this->purgePurgers->invalidate($this->processor, $invalidations);

        // Prepare and issue messages for each individual invalidation object.
        foreach ($invalidations as $invalidation) {
          $object = $invalidation->getType();
          if (!is_null($invalidation->getExpression())) {
            $object = $this->t('@object with expression "@expr"', [
              '@object' => $invalidation->getType(),
              '@expr' => (string) $invalidation->getExpression(),
            ]);
          }
          if ($invalidation->getState() === InvStatesInterface::SUCCEEDED) {
            $this->messenger()->addMessage($this->t('Successfully cleared @object.', ['@object' => $object]));
          }
          elseif ($invalidation->getState() === InvStatesInterface::PROCESSING) {
            $this->messenger()->addWarning($this->t('Requested to clear multistep object of type @object!', ['@object' => $object]));
          }
          else {
            $this->messenger()->addError($this->t('Failed to clear @object!', ['@object' => $object]));
          }
        }
      }
      catch (DiagnosticsException | CapacityException | LockException $e) {
        // Diagnostic exceptions happen when the system cannot purge, capacity
        // exceptions when too much was purged and lock exceptions when another
        // code path is still processing.
        $this->messenger()->addError($e->getMessage());
      }

      $response->addCommand(new ReloadConfigFormCommand('edit-purgers'));
    }

    return $response;
  }

  /**
   * Builds the expression field for the given invalidation type.
   *
   * @param string $type
   *   Invalidation plugin ID to build the expression field for.
   *
   * @return array
   *   Render array for the expression field.
   */
  protected function buildExpression($type) {
    $typedef = $this->purgeInvalidationFactory->getPlugins()[$type];

    $build = [
      '#type' => 'textarea',
      '#title' => $this->t('Expression'),
      '#rows' => 5,
      '#description' => $this->t('Provide one expression per line, each will be invalidated as the selected type.'),
      '#default_value' => '',
      '#required' => ($typedef['expression_required'] && !$typedef['expression_can_be_empty']),
    ];

    if (!empty($typedef['examples'])) {
      $build['#description'] = $this->t('@description For example: @examples.', [
        '@description' => $build['#description'],
        '@examples' => implode(', ', $typedef['examples']),
      ]);
    }

    if (!$typedef['expression_required']) {
      $build['#description'] = $this->t('The selected type does not take an expression.');
      $build['#disabled'] = TRUE;
    }

    return $build;
  }

  /**
   * Provides all invalidation types supported by the purger, as select options.
   *
   * @return array
   *   Type plugin IDs mapped to a label describing each type.
   */
  protected function getPurgerTypes() {
    $types_by_purger = $this->purgePurgers->getTypesByPurger();
    $types = array_flip($types_by_purger[$this->id] ?? []);
    foreach ($this->purgeInvalidationFactory->getPlugins() as $type => $typedef) {
      if (!isset($types[$type])) {
        continue;
      }
      $types[$type] = $this->t('@label - @description', [
        '@label' => $typedef['label'],
        '@description' => $typedef['description'],
      ]);
    }
    return $types;
  }

}
