<?php

namespace Drupal\content_moderation_notifications\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides a listing of content_moderation_notification entities.
 *
 * List Controllers provide a list of entities in a tabular form. The base
 * class provides most of the rendering logic for us. The key functions
 * we need to override are buildHeader() and buildRow(). These control what
 * columns are displayed in the table, and how each row is displayed
 * respectively.
 *
 * Drupal locates the list controller by looking for the "list" entry under
 * "controllers" in our entity type's annotation. We define the path on which
 * the list may be accessed in our module's *.routing.yml file. The key entry
 * to look for is "_entity_list". In *.routing.yml, "_entity_list" specifies
 * an entity type ID. When a user navigates to the URL for that router item,
 * Drupal loads the annotation for that entity type. It looks for the "list"
 * entry under "controllers" for the class to load.
 *
 * @package Drupal\content_moderation_notifications\Controller
 *
 * @ingroup content_moderation_notifications
 */
class ContentModerationNotificationsListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   *
   * @see Drupal\Core\Entity\EntityListController::render()
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['workflow'] = $this->t('Workflow');
    $header['transition'] = $this->t('Transitions');
    $header['status'] = $this->t('Status');
    $header['debug'] = $this->t('Debug');
    $header['roles'] = $this->t('Email Roles');
    $header['author'] = $this->t('Email Author');
    $header['config'] = $this->t('Email Configuration');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the entity.
   *
   * @see Drupal\Core\Entity\EntityListController::render()
   */
  public function buildRow(EntityInterface $entity) {

    // Load the workflow @todo change to dependency injection.
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = \Drupal::entityTypeManager()
      ->getStorage('workflow')
      ->load($entity->workflow);

    // Load the transitions in this workflow.
    $workflow_transitions = $workflow->getTypePlugin()->getTransitions();

    $row = [];

    // Array of transitions used in each row.
    $transition_strings = [];

    // Loop through the saved transitions.
    if ($entity->transitions) {
      $transitions = array_keys(array_filter($entity->transitions));
    }
    foreach ($transitions as $transition) {
      if (!empty($workflow_transitions[$transition])) {
        $transition_strings[] = $workflow_transitions[$transition]->label();
      }
    }

    $row['label'] = $entity->label();
    $row['workflow'] = $workflow->label();
    $row['transition'] = implode(', ', $transition_strings);
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['debug'] = $entity->debug ? $this->t('Debugging') : $this->t('No');

    $roles = [];
    if ($entity->roles) {
      $roles = array_keys(array_filter($entity->roles));
    }

    if ($entity->getTo()) {
      $headers[] = "<strong>TO:</strong> " . Html::escape($entity->getTo());
    }
    if ($entity->getCc()) {
      $headers[] = "<strong>CC:</strong> " . Html::escape($entity->getCc());
    }
    if ($entity->getBcc()) {
      $headers[] = "<strong>BCC:</strong> " . Html::escape($entity->getBcc());
    }
    if ($entity->getReplyTo()) {
      $headers[] = "<strong>REPLY-TO:</strong> " . Html::escape($entity->getReplyTo());
    }
    if ($entity->getFrom()) {
      $headers[] = "<strong>FROM:</strong> " . Html::escape($entity->getFrom());
    }
    if ($entity->getAbort()) {
      $headers[] = "<strong>ABORT:</strong> " . Html::escape($entity->getAbort());
    }
    if ($entity->getSubject()) {
      $headers[] = "<strong>SUBJECT:</strong> " . Html::escape($entity->getSubject());
    }
    if ($entity->getMessage()) {
      $headers[] = "<strong>BODY:</strong> " . Html::escape($entity->getMessage());
    }

    $row['roles'] = implode(', ', $roles);
    $row['author'] = $entity->author ? $this->t('Yes') : $this->t('No');
    $row['config'] = new FormattableMarkup(implode("<br />", $headers), []);
    return $row + parent::buildRow($entity);
  }

}
