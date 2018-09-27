<?php

namespace Drupal\forms_steps\Service;

/**
 * Class FormsStepsHelper.
 *
 * @package Drupal\forms_steps\Service
 */
class FormsStepsHelper {

  /** @var \Drupal\Core\Entity\EntityManager $entityManager */
  protected $formsStepsManager;

  /**
   * FormsStepsManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   */
  public function __construct(FormsStepsManager $formsStepsManager) {
    $this->formsStepsManager = $formsStepsManager;
  }

  /**
   * Get the workflow uuid from the current route if it is a forms steps route.
   *
   * @return bool|string
   */
  public function getWorkflowUuidFromRoute() {
    $route_match = \Drupal::routeMatch();

    $step = $this->formsStepsManager->getStepByRoute($route_match->getRouteName());

    // Only return the workflow uuid if the current route is a forms steps route.
    if ($step) {
      $uuid = $route_match->getParameter('uuid');

      return $uuid;
    }

    return FALSE;
  }

}
