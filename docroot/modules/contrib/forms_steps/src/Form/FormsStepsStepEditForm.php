<?php

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\forms_steps\StepInterface;
use Drupal\node\Entity\NodeType;

/**
 * Class FormsStepsStepEditForm.
 */
class FormsStepsStepEditForm extends FormsStepsFormBase {

  /**
   * The ID of the step that is being edited.
   *
   * @var string
   */
  protected $stepId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'forms_steps_step_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $forms_steps_step = NULL) {
    $this->stepId = $forms_steps_step;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->getEntity();
    $step = $forms_steps->getStep($this->stepId);

    $form['label']['#default_value'] = $step->label();

    $form['id']['#default_value'] = $this->stepId;
    $form['id']['#disabled'] = TRUE;

    $form['target_node_type']['#default_value'] = $step->NodeType();

    $form['target_form_mode']['#default_value'] = $step->formMode() ? $step->formMode() : 'default';

    $form['url']['#default_value'] = $step->Url();

    $form['submit_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Submit Button'),
      '#open' => FALSE,
    ];

    $form['submit_button']['override_submit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override submit label'),
      '#maxlength' => 255,
      '#default_value' => $step->submitLabel() ? TRUE : FALSE,
      '#required' => FALSE,
    ];

    $form['submit_button']['submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Submit label'),
      '#maxlength' => 255,
      '#default_value' => $step->submitLabel() ? $step->submitLabel() : NULL,
      '#description' => $this->t('Label of the submit button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_submit"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['delete_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Delete Button'),
      '#open' => FALSE,
    ];

    $form['delete_button']['hide_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide delete button'),
      '#maxlength' => 255,
      '#default_value' => $step->hideDelete() ? TRUE : FALSE,
      '#required' => FALSE,
    ];

    $form['delete_button']['override_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override delete label'),
      '#maxlength' => 255,
      '#default_value' => $step->deleteLabel() ? TRUE : FALSE,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="hide_delete"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['delete_button']['delete_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delete label'),
      '#maxlength' => 255,
      '#default_value' => $step->deleteLabel() ? $step->deleteLabel() : NULL,
      '#description' => $this->t('Label of the delete button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_delete"]' => [
            'checked' => TRUE,
          ],
          ':input[name="hide_delete"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['cancel_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Cancel Button'),
      '#open' => FALSE,
    ];

    $form['cancel_button']['cancelLabel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override cancel label'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelLabel() ? TRUE : FALSE,
      '#required' => FALSE,
    ];

    $form['cancel_button']['cancel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel label'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelLabel() ? $step->cancelLabel() : NULL,
      '#description' => $this->t('Label of the cancel button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_cancel"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['set_cancel_route'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set cancel route'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelRoute() ? TRUE : FALSE,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_step"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['cancel_route'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel route'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelRoute() ? $step->cancelRoute() : NULL,
      '#description' => '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_route"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['set_cancel_step'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set a step to go when cancel is clicked.'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelStep() ? TRUE : FALSE,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_route"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $steps = $forms_steps->getSteps();
    $steps_options = [];
    /** @var \Drupal\forms_steps\Step $step */
    foreach ($steps as $_step) {
      $steps_options[$_step->id()] = $_step->label();
    }

    $form['cancel_button']['cancel_step'] = [
      '#type' => 'select',
      '#title' => $this->t('Step'),
      '#maxlength' => 255,
      '#default_value' => $step->cancelStep() ? $step->cancelStep()
        ->id() : NULL,
      '#required' => FALSE,
      '#options' => $steps_options,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_step"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['previous_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Previous Button'),
      '#open' => FALSE,
    ];

    $form['previous_button']['display_previous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display previous button'),
      '#maxlength' => 255,
      '#default_value' => $step->displayPrevious() ? TRUE : FALSE,
      '#required' => FALSE,
    ];

    $form['previous_button']['previous_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous label'),
      '#maxlength' => 255,
      '#default_value' => $step->previousLabel() ? $step->previousLabel() : NULL,
      '#description' => $this->t('Label of the previous button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="display_previous"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    // Check that the form_id exists.
    $nodeType = NodeType::load($values['target_node_type']);

    if (is_null($nodeType)) {
      $form_state->setErrorByName('target_node_type', $this->t('Not a valid form'));
    }
    else {
      $entityType = 'node';
      $form_mode = preg_replace("/^$entityType\./", '', $values['target_form_mode']);
      $bundle = $values['target_node_type'];

      $entityFormDisplay = entity_get_form_display($entityType, $bundle, $form_mode);
      if ($entityFormDisplay->isNew()) {
        $form_state->setErrorByName(
          'target_form_mode',
          $this->t('This form mode is not yet defined for the selected bundle.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->entity;

    $forms_steps->save();
    drupal_set_message($this->t('Saved %label step.', [
      '%label' => $forms_steps->getStep($this->stepId)->label(),
    ]));
    $form_state->setRedirectUrl($forms_steps->toUrl('edit-form'));
  }

  /**
   * Copies top-level form values to entity properties
   *
   * This form can only change values for a step, which is part of forms_steps.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
    $values = $form_state->getValues();

    $entity->setStepLabel($values['id'], $values['label']);
    $entity->setStepNodeType($values['id'], $values['target_node_type']);
    $entity->setStepFormMode($values['id'], $values['target_form_mode']);
    $entity->setStepUrl($values['id'], $values['url']);

    if ($values['override_submit'] == 1) {
      $entity->setStepSubmitLabel($values['id'], $values['submit_label']);
    }
    else {
      $entity->setStepSubmitLabel($values['id'], NULL);
    }

    if ($values['hide_delete'] == 1) {
      $entity->setStepDeleteState($values['id'], TRUE);
    }
    else {
      $entity->setStepDeleteState($values['id'], FALSE);

      if ($values['override_delete'] == 1) {
        $entity->setStepDeleteLabel($values['id'], $values['delete_label']);
      }
      else {
        $entity->setStepDeleteLabel($values['id'], NULL);
      }
    }

    if ($values['cancelLabel'] == 1) {
      $entity->setStepCancelLabel($values['id'], $values['cancel']);

      if ($values['set_cancel_route'] == 1) {
        $entity->setStepCancelStep($values['id'], NULL);
        $entity->setStepCancelStepMode($values['id'], NULL);

        $entity->setStepCancelRoute($values['id'], $values['cancel_route']);
      }
      else {
        if ($values['set_cancel_step'] == 1) {
          $entity->setStepCancelRoute($values['id'], NULL);

          $entity->setStepCancelStep($values['id'], $entity->getStep($values['cancel_step']));
        }
      }
    }
    else {
      $entity->setStepCancelLabel($values['id'], NULL);
    }

    if ($values['display_previous'] == 1) {
      $entity->setStepPreviousState($values['id'], TRUE);
      $entity->setStepPreviousLabel($values['id'], $values['previous_label']);
    }
    else {
      $entity->setStepPreviousState($values['id'], FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm', '::save'],
    ];

    $actions['delete'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#access' => $this->entity->access('delete-state:' . $this->stepId),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
      '#url' => Url::fromRoute('entity.forms_steps.delete_step_form', [
        'forms_steps' => $this->entity->id(),
        'forms_steps_step' => $this->stepId,
      ]),
    ];

    return $actions;
  }

}
