<?php

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\forms_steps\Entity\FormsSteps;

/**
 * Provides a form to edit a Forms Steps.
 */
class FormsStepsEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$this->entity->isNew(),
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'source' => ['label'],
        'error' => $this->t('The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".'),
      ],
    ];

    $header = [
      'step' => $this->t('Step'),
      'form_id' => $this->t('Node type'),
      'form_mode' => $this->t('Form mode'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];
    $form['steps_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Steps'),
      '#open' => TRUE,
      '#collapsible' => 'FALSE',
    ];
    $form['steps_container']['steps'] = [
      '#type' => 'table',
      '#header' => $header,
      '#title' => $this->t('Steps'),
      '#empty' => $this->t('There are no steps yet.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'step-weight',
        ],
      ],
    ];

    $steps = $forms_steps->getSteps();

    // Warn the user if there are no steps.
    if (empty($steps)) {
      drupal_set_message(
        $this->t(
          'This Forms Steps has no steps and will be disabled until there is at least one, <a href=":add-step">add a new node step.</a>',
          [':add-step' => $forms_steps->toUrl('add-step-form')->toString()]
        ),
        'warning'
      );
    }

    foreach ($steps as $step) {
      $links = [
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity.forms_steps.edit_step_form', [
            'forms_steps' => $forms_steps->id(),
            'forms_steps_step' => $step->id(),
          ]),
          'attributes' => ['aria-label' => $this->t('Edit @step step', ['@step' => $step->label()])],
        ],
      ];
      if ($this->entity->access('delete-step:' . $step->id())) {
        $links['delete'] = [
          'title' => t('Delete'),
          'url' => Url::fromRoute('entity.forms_steps.delete_step_form', [
            'forms_steps' => $forms_steps->id(),
            'forms_steps_step' => $step->id(),
          ]),
          'attributes' => ['aria-label' => $this->t('Delete @step step', ['@step' => $step->label()])],
        ];
      }
      $form['steps_container']['steps'][$step->id()] = [
        '#attributes' => ['class' => ['draggable']],
        'step' => ['#markup' => $step->label()],
        'form_id' => ['#markup' => $step->NodeType()],
        'form_mode' => ['#markup' => $step->formMode()],
        '#weight' => $step->weight(),
        'weight' => [
          '#type' => 'weight',
          '#title' => t('Weight for @title', ['@title' => $step->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $step->weight(),
          '#attributes' => ['class' => ['step-weight']],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
    }
    $form['steps_container']['step_add'] = [
      '#markup' => $forms_steps->toLink($this->t('Add a new node step'), 'add-step-form')
        ->toString(),
    ];

    $form['progress_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Progress bar'),
      '#description' => $this->t(
        'Define new progress steps here and assign steps to them to generate a progress bar block available for display.<br/>To configure the block display, please go to the <a href=":block-layout-url">block layout section</a>.<br/><br/><em>Note that any link set to be displayed on the first step will not be rendered, as Forms Steps starts to store progression on the first step submission.</em>',
          [':block-layout-url' => Url::fromRoute('block.admin_display')->toString()]
        ),
      '#open' => TRUE,
      '#collapsible' => 'FALSE',
    ];

    $header = [
      'progress_step' => $this->t('progress step'),
      'routes' => $this->t('Active for steps'),
      'link' => $this->t('Link'),
      'link_visibility' => $this->t('Link visibility'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];

    $form['progress_container']['progress_steps'] = [
      '#type' => 'table',
      '#header' => $header,
      '#title' => $this->t('progress steps'),
      '#empty' => $this->t('There are no progress steps yet.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'progress-state-weight',
        ],
      ],
    ];

    // Warn the user if there are no steps.
    if (empty($steps)) {
      $form['progress_container']['no_steps'] = [
        '#markup' => $this->t(
          'This Forms Steps has no steps, no progress step can be added until there is at least one, <a href=":add-step">add a new node step.</a>',
          [':add-step' => $forms_steps->toUrl('add-step-form')->toString()]
        ),
      ];
    }
    else {
      $progress_steps = $forms_steps->getProgressSteps();

      foreach ($progress_steps as $progress_step) {
        // Defines admin links.
        $links = [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('entity.forms_steps.edit_progress_step_form', [
              'forms_steps' => $forms_steps->id(),
              'forms_steps_progress_step' => $progress_step->id(),
            ]),
            'attributes' => ['aria-label' => $this->t('Edit @progress_step progress step', ['@progress_step' => $progress_step->label()])],
          ],
        ];
        if ($this->entity->access('delete-progress-step:' . $progress_step->id())) {
          $links['delete'] = [
            'title' => t('Delete'),
            'url' => Url::fromRoute('entity.forms_steps.delete_progress_step_form', [
              'forms_steps' => $forms_steps->id(),
              'forms_steps_progress_step' => $progress_step->id(),
            ]),
            'attributes' => ['aria-label' => $this->t('Delete @progress_step progress_step', ['@progress_step' => $progress_step->label()])],
          ];
        }

        // Defines active routes.
        $routes = [];
        $active_routes = $progress_step->activeRoutes();
        $active_routes = array_filter($active_routes);
        $active_routes = $forms_steps->getSteps($active_routes);

        foreach($active_routes as $key => $value) {
          $routes[] = $value->label();
        }

        if (!count($routes)) {
          $routes = $this->t('No step assigned on this progress step');
        }
        else {
          $routes = implode(', ', $routes);
        }

        // Defines link.
        if (empty($progress_step->link())) {
          $link = $this->t('No link defined');
        }
        else {
          $step_id = $progress_step->link();
          $link = $forms_steps->getStep($step_id)->label();
        }

        // Defines link visibility.
        $steps_ids = array_filter($progress_step->linkVisibility());
        if (empty($steps_ids)) {
          $link_visibility = $this->t('No link displayed');
        }
        else {
          $steps = $forms_steps->getSteps($steps_ids);

          $link_visibility = [];
          foreach ($steps as $step) {
            $link_visibility[] = $step->label();
          }
          $link_visibility = implode(', ', $link_visibility);
        }

        $form['progress_container']['progress_steps'][$progress_step->id()] = [
          '#attributes' => ['class' => ['draggable']],
          'progress_step' => ['#markup' => $progress_step->label()],
          'routes' => ['#markup' => $routes],
          'link' => ['#markup' => $link],
          'link_visibility' => ['#markup' => $link_visibility],
          '#weight' => $progress_step->weight(),
          'weight' => [
            '#type' => 'weight',
            '#title' => t('Weight for @title', ['@title' => $progress_step->label()]),
            '#title_display' => 'invisible',
            '#default_value' => $progress_step->weight(),
            '#attributes' => ['class' => ['progress-state-weight']],
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }
      $form['progress_container']['progress_add'] = [
        '#markup' => $forms_steps->toLink($this->t('Add a new progress step'), 'add-progress-step-form')
          ->toString(),
      ];
    }


    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => FALSE,
    ];

    $form['settings']['description'] = [
      '#type' => 'textarea',
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('Enter a description for this Forms Steps.'),
      '#title' => $this->t('Description'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    $actions['cancel'] = [
      '#type' => 'submit',
      '#limit_validation_errors' => [['locked']],
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // This form can only set the forms steps ID, label and the weights
    // for each step.
    /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
    $values = $form_state->getValues();
    $entity->set('label', $values['label']);
    $entity->set('id', $values['id']);
    $entity->set('description', $values['description']);

    if (!empty($values['steps'])) {
      foreach ($values['steps'] as $step_id => $step_values) {
        $entity->setStepWeight($step_id, $step_values['weight']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->urlInfo('edit-form'));

    drupal_set_message($this->t('Forms Steps %label has been updated.', ['%label' => $this->entity->label()]));
  }

  /**
   * Form submission handler for the 'cancel' action.
   *
   * @param array $form
   *   Form to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Forms States to alter.
   */
  public function cancel(array $form, FormStateInterface $form_state) {
    drupal_set_message($this->t('Canceled.'));
    $form_state->setRedirect('forms_steps.collection');
  }

  /**
   * Title callback: also display the Forms Steps label.
   *
   * @param \Drupal\forms_steps\Entity\FormsSteps $forms_steps
   *   Forms Steps to get label from.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translatable Title.
   */
  public function getTitle(FormsSteps $forms_steps) {
    return $this->t('Edit Forms Steps "@label"', ['@label' => $forms_steps->label()]);
  }

}
