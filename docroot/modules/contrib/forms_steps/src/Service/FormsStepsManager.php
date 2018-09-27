<?php

namespace Drupal\forms_steps\Service;


use Drupal\Core\Entity\EntityManager;
use Drupal\forms_steps\Entity\FormsSteps;

/**
 * Class FormsStepsManager.
 *
 * @package Drupal\forms_steps\Service
 */
class FormsStepsManager {

  /** @var \Drupal\Core\Entity\EntityManager $entityManager */
  protected $entityManager;

  /**
   * FormsStepsManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   */
  public function __construct(EntityManager $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * Get the forms_steps next route step.
   *
   * @param mixed $route_name
   *   Current route name.
   *
   * @return null|string
   *   Returns the next route.
   */
  public function getNextStepRoute($route_name) {
    $nextRoute = NULL;

    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return $nextRoute;
      }

      $step = $formsSteps->getStep($matches[2]);

      if (!$step) {
        return $nextRoute;
      }

      return $formsSteps->getNextStepRoute($step);
    }
  }

  /**
   * Get the forms_steps next step.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|\Drupal\forms_steps\Step
   *   Next Step.
   */
  public function getNextStep($route_name) {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        /** @var \Drupal\forms_steps\Step $nextStep */
        return $formsSteps->getNextStep($formsSteps->getStep($matches[2]));
      }
    }

    return NULL;
  }

  /**
   * Get the forms_steps previous route step.
   *
   * @param mixed $route_name
   *   Current route name.
   *
   * @return null|string
   *   Returns the previous route.
   */
  public function getPreviousStepRoute($route_name) {
    $previousRoute = NULL;

    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return $previousRoute;
      }

      $step = $formsSteps->getStep($matches[2]);

      if (!$step) {
        return $previousRoute;
      }

      return $formsSteps->getPreviousStepRoute($step);
    }
  }

  /**
   * Get the forms_steps previous step.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|\Drupal\forms_steps\Step
   *   Previous Step.
   */
  public function getPreviousStep($route_name) {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        /** @var \Drupal\forms_steps\Step $nextStep */
        return $formsSteps->getPreviousStep($formsSteps->getStep($matches[2]));
      }
    }

    return NULL;
  }

  /**
   * Get the forms_steps step by route.
   *
   * @param mixed $route_name
   *   Current route.
   *
   * @return null|string
   *   Returns the Step of the route.
   */
  public function getStepByRoute($route_name) {
    $matches = self::getRouteParameters($route_name);
    if ($matches) {

      /** @var FormsSteps $formsSteps */
      $formsSteps = FormsSteps::load($matches[1]);
      if (!$formsSteps) {
        return NULL;
      }

      return $formsSteps->getStep($matches[2]);
    }

    return NULL;
  }

  /**
   * Returns route parameters.
   *
   * @param $route_name
   *   Route to get the parameters from.
   *
   * @return array|false
   *   Parameters of the route.
   */
  public function getRouteParameters($route_name) {
    // forms_steps routes using the format: forms_steps.forms_steps_id.step_id
    $route_pattern = '/^forms_steps\.([a-zA-Z0-9_]{1,})\.([a-zA-Z0-9_]{1,})/';

    if (preg_match($route_pattern, $route_name, $matches) == 1) {
      return $matches;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get all form modes per entity type. Only managing node for the moment.
   *
   * @return array
   */
  public function getAllFormModesDefinitions() {
    // Only managing node at this time. Improvment require.
    $all_form_modes = [];
    $form_modes = $this->entityManager->getFormModes('node');

    foreach ($form_modes as $key => $value) {
      if (!empty($key) && $value['targetEntityType'] === 'node') {
        $all_form_modes['node'][] = $key;
      }
    }

    return $all_form_modes;
  }

}
