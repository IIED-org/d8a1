<?php

namespace Drupal\forms_steps\Form;

use Drupal\Core\Form\FormState;
use Drupal\forms_steps\Step;
use Drupal\forms_steps\StepInterface;

/**
 * Class FormsStepsAlter.
 *
 * @package Drupal\forms_steps\Form
 */
class FormsStepsAlter {

  /**
   * @param array $form
   *  Form to handle.
   * @param \Drupal\Core\Form\FormState $form_state
   *  Form State to handle.
   */
  public static function handle(array &$form, FormState $form_state) {
    /** @var \Drupal\forms_steps\Service\FormsStepsManager $formsStepsManager */
    $formsStepsManager = \Drupal::service('forms_steps.manager');

    /** @var \Drupal\forms_steps\Step $step */
    $step = $formsStepsManager->getStepByRoute(\Drupal::routeMatch()
      ->getRouteName());

    // We define the buttons label.
    FormsStepsAlter::setButtonLabel($step, $form);

    // We manage the previous/next buttons.
    FormsStepsAlter::handleNavigation($step, $form);
  }

  /**
   * Define the submit and cancel label using the step configuration.
   *
   * @param Step $step
   *   Current Step.
   * @param array $form
   *   Form to alter.
   */
  public static function setButtonLabel(Step $step, array &$form) {
    if ($step) {
      if ($step->submitLabel()) {
        $form['actions']['submit']['#value'] = t($step->submitLabel());
      }

      if ($step->cancelLabel()) {
        $form['actions']['cancel']['#value'] = t($step->cancelLabel());
      }
    }
  }

  /**
   * Manage previous/next actions.
   *
   * @param Step $step
   *  Current Step.
   * @param array $form
   *  Form to alter.
   */
  public static function handleNavigation(Step $step, array &$form) {
    if ($step) {
      if ($step->displayPrevious()) {
        $form['actions']['previous'] =
        [
          '#type' => 'submit',
          '#value' => t($step->previousLabel()),
          '#name' => 'previous_action',
          '#submit' => [
            ['Drupal\forms_steps\Form\FormsStepsAlter', 'setPreviousRoute']
          ],
          '#limit_validation_errors' => [['op']],
        ];

      }
    }
  }

  /**
   * Redirect to the next step if one exists.
   *
   * @param array $form
   *   Form to alter.
   * @param \Drupal\Core\Form\FormState $form_state
   *   Form State to update.
   */
  public static function setNextRoute(array &$form, FormState $form_state) {
    /** @var \Drupal\forms_steps\Service\FormsStepsManager $formsStepsManager */
    $formsStepsManager = \Drupal::service('forms_steps.manager');
    $nextRoute = $formsStepsManager->getNextStepRoute(\Drupal::routeMatch()
      ->getRouteName());

    if ($nextRoute) {
      $form_state->setRedirect($nextRoute, [
        'uuid' => $form_state->getFormObject()
          ->getEntity()
          ->getTypedData()
          ->get('field_forms_steps_id')
          ->getString(),
      ]);
    }
  }

  /**
   * Redirect the form to the previous step.
   *
   * @param array $form
   *  Form to alter.
   * @param \Drupal\Core\Form\FormState $form_state
   *  Forms State to Update.
   */
  public static function setPreviousRoute(array &$form, FormState $form_state) {
    /** @var \Drupal\forms_steps\Service\FormsStepsManager $formsStepsManager */
    $formsStepsManager = \Drupal::service('forms_steps.manager');

    /** @var \Drupal\forms_steps\Step $step */
    $step = $formsStepsManager->getPreviousStepRoute(\Drupal::routeMatch()
      ->getRouteName());

    // Check if this is an add step
    $uuid = $form_state->getFormObject()
      ->getEntity()
      ->getTypedData()
      ->get('field_forms_steps_id')
      ->getString();

    if ($step) {
      $params = [];
      if (!empty($uuid)) {
        $params = ['uuid' => $uuid];
      }
      $form_state->setRedirect($step, $params);
    }
  }

}
