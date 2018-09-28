<?php

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\forms_steps\FormsStepsEvent;
use Drupal\forms_steps\StepInterface;

/**
 * Class FormsStepsStepAddForm.
 */
class FormsStepsStepAddForm extends FormsStepsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'forms_steps_step_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Can't use form_id as name - used by the system.
    // We retrieve a list of content types.
    $form['target_node_type']['#default_value'] = '';

    return $form;
  }

  /**
   * Determines if the forms steps step already exists.
   *
   * @param string $step_id
   *   The forms steps step ID.
   *
   * @return bool
   *   TRUE if the forms steps step exists, FALSE otherwise.
   */
  public function exists($step_id) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $original_forms_steps */
    $original_forms_steps = \Drupal::entityTypeManager()
      ->getStorage('forms_steps')
      ->loadUnchanged($this->getEntity()->id());
    return $original_forms_steps->hasStep($step_id);
  }

  /**
   * Copies top-level form values to entity properties.
   *
   * This form can only change values for a step, which is part of
   * forms_steps.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current step of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
    $values = $form_state->getValues();

    // This is fired twice so we have to check that the entity does not already
    // have the step.
    if (!$entity->hasStep($values['id'])) {
      $entity->addStep($values['id'], $values['label'], $values['target_node_type'], $values['target_form_mode'], $values['url']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    // Check that the form_id exists
    $nodeType = \Drupal\node\Entity\NodeType::load($values['target_node_type']);

    if (is_null($nodeType)) {
      $form_state->setErrorByName('target_node_type', $this->t('Not a valid bundle'));
    }
    else {
      $entityType = 'node';

      $entityFormDisplay = entity_get_form_display($entityType, $values['target_node_type'], preg_replace("/^$entityType\./", '', $values['target_form_mode']));
      if ($entityFormDisplay->isNew()) {
        $form_state->setErrorByName('target_form_mode', $this->t('This form mode is not yet defined for the selected bundle.'));
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

    // TODO: Check if there is a way to just update the current route ?!
    /** @var \Drupal\Core\Routing\RouteBuilder $routeBuilderService */
    $routeBuilderService = \Drupal::service('router.builder');
    $routeBuilderService->rebuild();

    drupal_set_message($this->t('Created %label step.', [
      '%label' => $forms_steps->getStep($form_state->getValue('id'))
        ->label(),
    ]));
    $form_state->setRedirectUrl($forms_steps->toUrl('edit-form'));
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
    return $actions;
  }

}
