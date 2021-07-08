<?php

namespace Drupal\forms_steps\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\forms_steps\Entity\Workflow;
use Drupal\forms_steps\Exception\AccessDeniedException;
use Drupal\forms_steps\Exception\FormsStepsNotFoundException;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * @param null|int $instance_id
   *   Instance id of the forms steps ref to load.
   *
   * @return mixed
   *   Form that match the input parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   */
  public function step($forms_steps, $step, $instance_id = NULL) {
    return self::getForm($forms_steps, $step, $instance_id);
  }

  /**
   * Get a form based on the $step and $nid.
   *
   * If $nid is empty or not existing we provide a create form, we edit
   * otherwise.
   *
   * TODO: De we need to move it in a service?
   *
   * @param int $forms_steps
   *   Forms Steps id to get the form from.
   * @param mixed $step
   *   Step to get the Form from.
   * @param null|int $instance_id
   *   Instance ID of the forms steps reference to load.
   *
   * @return \Drupal\Core\Render\Element\Form
   *   Returns the Form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public static function getForm($forms_steps, $step, $instance_id = NULL) {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
    $formsSteps = \Drupal::entityTypeManager()
      ->getStorage('forms_steps')
      ->load($forms_steps);

    if (!$formsSteps->hasStep($step)) {
      // TODO: Propose a better error management.
      throw new \InvalidArgumentException("The Step '$step' does not exist in forms steps '{$forms_steps}'");
    }

    $step = $formsSteps->getStep($step);

    $entity_key_type = \Drupal::entityTypeManager()
      ->getDefinition($step->entityType())
      ->getKey('bundle');

    // We initialize the entity with its potential last revision.
    $entity = NULL;
    $entities = [];
    if (!is_null($instance_id)) {
      try {
        $entities = \Drupal::entityTypeManager()
          ->getStorage(Workflow::ENTITY_TYPE)
          ->loadByProperties(['instance_id' => $instance_id]);
      }
      catch (\Exception $ex) {
      }
      if ($entities) {
        // We look for the same entity bundle.
        foreach ($entities as $_entity) {
          if (strcmp($_entity->entity_type->value, $step->entityType()) == 0
          && strcmp($_entity->bundle->value, $step->entityBundle()) == 0) {
            // We load the entity.
            $storage = \Drupal::entityTypeManager()->getStorage($_entity->entity_type->value);
            $idKey = $storage->getEntityType()->getKey('id');

            if ($storage->getEntityType()->isRevisionable() == FALSE) {
              $revision = NULL;
            }
            else {
              $revision = $storage->getQuery()
                ->condition($idKey, $_entity->entity_id->value)
                ->latestRevision()
                ->execute();
            }

            if ( $revision ) {
              $rid = key($revision);
              $entity = $storage->loadRevision($rid);
            }
            else {
              $entity = $storage->load($_entity->entity_id->value);
            }
            break;
          }
        }
      }
    }

    $userRegistrationAccess = FALSE;
    if ($step->entityType() == 'user') {
      $account = User::load(\Drupal::currentUser()->id());
      /** @var  \Drupal\Core\Access\AccessResultInterface $registrationAccess */
      $registrationAccess = \Drupal::service('access_check.user.register')
        ->access($account);
      $userRegistrationAccess = $registrationAccess->isAllowed();
    }

    // If entity not found, this is a new entity to create.
    if (is_null($entity)) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($step->entityType())
        ->create([$entity_key_type => $step->entityBundle()]);

      if ($entity) {
        if (!empty($instance_id)) {
          if (count($entities) == 0) {
            // No Forms Steps exists with that UUID - Error.
            throw new FormsStepsNotFoundException(t('No multi-step instance found.'));
          }
        }
        else {
          if (
            ($step->entityType() !== 'user' && !$entity->access('create')) ||
            ($step->entityType() === 'user' && !($userRegistrationAccess || $entity->access('create')))
          ){
            throw new AccessDeniedHttpException();
          } else if($formsSteps->getFirstStep()->id() != $step->id()) {
            throw new AccessDeniedException(t('First step of the multi-step forms is required.'));
          }
        }
      }
    } else {
      if (
        ($step->entityType() !== 'user' && !$entity->access('update')) ||
        ($step->entityType() === 'user' && !($entity->access('update')))
      ) {
        throw new AccessDeniedException(t('First step of the multi-step forms is required.'));
      }
    }

    $formMode = preg_replace("/^{$step->entityType()}\./", '', $step->formMode());
    try {
      // We load the form.
      $form = \Drupal::service('entity.form_builder')
        ->getForm(
          $entity,
          $formMode,
          ['form_steps' => TRUE]
        );
    }
    catch (InvalidPluginDefinitionException $e) {
      $entityTypeId = $entity->getEntityTypeId();
      $formModeOptions = \Drupal::service('entity_display.repository')
        ->getFormModeOptions($entityTypeId);

      if (isset($formModeOptions[$formMode])) {
        \Drupal::messenger()->addError("Site's cache must be cleared after adding new form mode:" . $formMode . " on " . $entityTypeId);
      }
      else {
        \Drupal::messenger()->addWarning($e->getMessage() . 'The form class could not be loaded.');
      }
      throw new NotFoundHttpException();
    }

    // Hiding the button following to the configuration.
    if ($step->hideDelete()) {
      unset($form['actions']['delete']);
    }
    elseif ($step->deleteLabel()) {
      $form['actions']['delete']['#title'] = t($step->deleteLabel());
    }

    // Return the form.
    return $form;
  }

}
