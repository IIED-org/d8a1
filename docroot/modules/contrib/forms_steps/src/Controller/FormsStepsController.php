<?php

namespace Drupal\forms_steps\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\forms_steps\Exception\AccessDeniedException;
use Drupal\forms_steps\Exception\FormsStepsNotFoundException;

/**
 * Class FormsStepsController.
 *
 * @package Drupal\forms_steps\Controller
 */
class FormsStepsController extends ControllerBase {

  /**
   * Display the step form.
   *
   * @param int $forms_steps
   *   Forms Steps id to display step from.
   * @param mixed $step
   *   Step to display.
   * @param null|int $uuid
   *   UUID id of the forms steps ref to load.
   *
   * @return mixed
   *   Form that match the input parameters.
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   * @throws \InvalidArgumentException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   */
  public function step($forms_steps, $step, $uuid = NULL) {
    return self::getForm($forms_steps, $step, $uuid);
  }

  /**
   * Get a form based on the $step and $nid. If $nid is empty or not existing
   * we provide a create form. edit otherwise.
   *
   * TODO: De we need to move it in a service?
   *
   * @param int $forms_steps
   *   Forms Steps id to get the form from.
   * @param mixed $step
   *   Step to get the Form from.
   * @param null|int $uuid
   *   UUID of the forms steps reference to load.
   *
   * @return mixed
   *   Form that match the input parameters.
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   * @throws \InvalidArgumentException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   */
  public static function getForm($forms_steps, $step, $uuid = NULL) {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
    $formsSteps = \Drupal::entityTypeManager()
      ->getStorage('forms_steps')
      ->load($forms_steps);

    if (!$formsSteps->hasStep($step)) {
      // TODO: Propose a better error management.
      throw new \InvalidArgumentException("The Step '$step' does not exist in forms steps '{$forms_steps}'");
    }

    $step = $formsSteps->getStep($step);

    $entity_type = 'node';
    $entity_key_type = \Drupal::entityTypeManager()
      ->getDefinition($entity_type)
      ->getKey('bundle');

    // We create the entity.
    $entity = NULL;
    $entities = [];
    if (!is_null($uuid)) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->loadByProperties(['field_forms_steps_id' => $uuid]);
      if ($entities) {
        foreach ($entities as $_entity) {
          if (strcmp($_entity->bundle(), $step->nodeType()) == 0) {
            $entity = $_entity;
            break;
          }
        }
      }
    }

    if (is_null($entity)) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->create([$entity_key_type => $step->nodeType()]);

      if ($entity) {
        if (!empty($uuid)) {
          if (count($entities) == 0) {
            // No Forms Steps exists with that UUID - Error.
            throw new FormsStepsNotFoundException(t('No multi-step found.'));
          }
          // FormsSteps already instancied.
          // Associate the new entity to it.
          $entity->getTypedData()->set(
            'field_forms_steps_id',
            $uuid
          );
        }
        else {
          if ($formsSteps->getFirstStep()->id() != $step->id()) {
            throw new AccessDeniedException(t('First step of the multi-step forms is required.'));
          }
        }
      }
    }

    $form = \Drupal::service('entity.form_builder')
      ->getForm($entity, preg_replace("/^$entity_type\./", '', $step->formMode()));


    if ($step->hideDelete()) {
      unset($form['actions']['delete']);
    }
    elseif ($step->deleteLabel()) {
      $form['actions']['delete']['#title'] = t($step->deleteLabel());
    }

    return $form;
  }

}
