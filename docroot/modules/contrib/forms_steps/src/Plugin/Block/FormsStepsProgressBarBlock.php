<?php

namespace Drupal\forms_steps\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the progress bar block.
 *
 * @Block(
 *   id = "forms_steps_progress_bar",
 *   admin_label = @Translation("Forms Steps - Progress bar"),
 *   deriver = "Drupal\forms_steps\Plugin\Derivative\FormsStepsProgressBarBlock"
 * )
 */
class FormsStepsProgressBarBlock extends BlockBase implements ContainerFactoryPluginInterface {
  private $derivativeId;

  /**
   * Creates a forms steps block instance.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param EntityManagerInterface $entity_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->derivativeId = $this->getDerivativeId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route = \Drupal::routeMatch()->getRouteName();

    /** @var \Drupal\forms_steps\Service\FormsStepsManager $formsStepsManager */
    $formsStepsManager = \Drupal::service('forms_steps.manager');

    /** @var \Drupal\forms_steps\Step $step */
    $step = $formsStepsManager->getStepByRoute($route);

    // The block is rendered only if the current route is a forms steps route.
    if ($step) {
      /** @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
      $forms_steps = $step->formsSteps();

      // If the derivative id is the current step, we display
      // the corresponding progress steps.
      if ($forms_steps->id() === $this->derivativeId) {
        $items = [];
        $item_class = 'previous-step';
        foreach ($forms_steps->getProgressSteps() as $progress_step) {
          $item = [];

          // Prepare the current progress step content regarding
          // the existence of a link and its visibility configuration.
          $link_visibility = array_filter($progress_step->linkVisibility());

          // Retrieve current workflow uuid to add it to the link.
          $uuid = \Drupal::service('forms_steps.helper')->getWorkflowUuidFromRoute();

          // Display a simple label or the link.
          // @todo: Manage the specific case of "No workflow uuid" for the first step to avoid having no links at all on this step.
          if (empty($progress_step->link()) || !in_array($step->id(), $link_visibility) || empty($uuid)) {
            $item['#markup'] = $this->t($progress_step->label());
          } else {
            $link_step = $forms_steps->getStep($progress_step->link());
            $options = [];
            if ($uuid) {
              $options['uuid'] = $uuid;
            }
            $url = Url::fromRoute($forms_steps->getStepRoute($link_step), $options);
            $link = Link::fromTextAndUrl($this->t($progress_step->label()), $url);
            $toRenderable = $link->toRenderable();
            $markup = render($toRenderable);

            $item['#markup'] = $markup->__toString();
          }
          $routes = $progress_step->activeRoutes();
          array_filter($routes);

          // Defined the active status.
          $active = FALSE;
          foreach ($routes as $route) {
            if ($route === $step->id()) {
              $active = TRUE;
              break;
            }
          }

          // Set classes.
          if ($active) {
            $item['#wrapper_attributes']['class'][] = 'active';
            $item_class = 'next-step';
          }
          else {
            $item['#wrapper_attributes']['class'][] = $item_class;
          }

          // Add item to the items list.
          $items[] = $item;
        }

        return [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#title' => '',
          '#items' => $items,
          '#cache' => [
            'max-age' => 0,
          ],
        ];
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['progress_bar_settings'] = $form_state->getValue('progress_bar_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $route = \Drupal::routeMatch()->getRouteName();

    /** @var \Drupal\forms_steps\Service\FormsStepsManager $formsStepsManager */
    $formsStepsManager = \Drupal::service('forms_steps.manager');

    /** @var \Drupal\forms_steps\Step $step */
    $step = $formsStepsManager->getStepByRoute($route);

    // Rebuild cache if the step is a new one.
    if ($step) {
      return Cache::mergeTags(parent::getCacheTags(), array('forms_steps_step:' . $step->id()));
    } else {
      return parent::getCacheTags();
    }
  }

  public function getCacheContexts() {
    // Set cache context as we depend on routes.
    return Cache::mergeContexts(parent::getCacheContexts(), array('route'));
  }
}
