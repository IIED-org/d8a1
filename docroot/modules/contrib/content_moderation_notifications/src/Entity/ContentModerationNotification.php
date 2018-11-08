<?php

namespace Drupal\content_moderation_notifications\Entity;

use Drupal\content_moderation_notifications\ContentModerationNotificationInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the content_moderation_notification entity.
 *
 * @see http://previousnext.com.au/blog/understanding-drupal-8s-config-entities
 * @see annotation
 * @see Drupal\Core\Annotation\Translation
 *
 * @ingroup content_moderation_notifications
 *
 * @ConfigEntityType(
 *   id = "content_moderation_notification",
 *   label = @Translation("Notification"),
 *   admin_permission = "administer content moderation notifications",
 *   handlers = {
 *     "access" = "Drupal\content_moderation_notifications\ContentModerationNotificationsAccessController",
 *     "list_builder" = "Drupal\content_moderation_notifications\Controller\ContentModerationNotificationsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\content_moderation_notifications\Form\ContentModerationNotificationsAddForm",
 *       "edit" = "Drupal\content_moderation_notifications\Form\ContentModerationNotificationsEditForm",
 *       "delete" = "Drupal\content_moderation_notifications\Form\ContentModerationNotificationsDeleteForm",
 *       "disable" = "Drupal\content_moderation_notifications\Form\DisableForm",
 *       "enable" = "Drupal\content_moderation_notifications\Form\DisableForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/workflow/notifications/add",
 *     "edit-form" = "/admin/config/workflow/notifications/manage/{content_moderation_notification}",
 *     "delete-form" = "/admin/config/workflow/notifications/manage/{content_moderation_notification}/delete",
 *     "enable-form" = "/admin/config/workflow/notifications/manage/{content_moderation_notification}/enable",
 *     "disable-form" = "/admin/config/workflow/notifications/manage/{content_moderation_notification}/disable",
 *     "collection" = "/admin/config/workflow/notifications"
 *   },
 *   config_export = {
 *     "id",
 *     "workflow",
 *     "transitions",
 *     "roles",
 *     "author",
 *     "to",
 *     "cc",
 *     "bcc",
 *     "from",
 *     "replyto",
 *     "subject",
 *     "body",
 *     "label",
 *     "abort",
 *     "debug",
 *   }
 * )
 */
class ContentModerationNotification extends ConfigEntityBase implements ContentModerationNotificationInterface {

  /**
   * Send notification to the revision author.
   *
   * @var bool
   */
  public $author = FALSE;

  /**
   * The notification body value and format.
   *
   * @var array
   */
  public $body = [
    'value' => '',
    'format' => '',
  ];

  /**
   * TO address(es) email header.
   *
   * @var string
   */
  public $to = '';

  /**
   * CC address(es) email header.
   *
   * @var string
   */
  public $cc = '';

  /**
   * BCC address(es) email header.
   *
   * @var string
   */
  public $bcc = '';

  /**
   * FROM address(es) email header.
   *
   * @var string
   */
  public $from = '';

  /**
   * REPLY-TO address(es) email header.
   *
   * @var string
   */
  public $replyto = '';

  /**
   * Abort-sending email address.
   *
   * @var string
   */
  public $abort = '';

  /**
   * Process in Debug mode (display headers, no sending mail).
   *
   * @var bool
   */
  public $debug = FALSE;

  /**
   * The role IDs to send notifications to.
   *
   * @var string[]
   */
  public $roles = [];

  /**
   * The message subject.
   *
   * @var string
   */
  public $subject;

  /**
   * The transition IDs relevant to this notification.
   *
   * @var string[]
   */
  public $transitions = [];

  /**
   * The associated workflow for these notifications.
   *
   * @var string
   */
  public $workflow;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId() {
    return $this->get('workflow');
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleIds() {
    return $this->get('roles');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->set('roles', array_filter($this->get('roles')));
    $this->set('transitions', array_filter(($this->get('transitions'))));
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions() {
    return $this->get('transitions');
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->get('subject');
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('body')['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageFormat() {
    return $this->get('body')['format'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTo() {
    return $this->get('to');
  }

  /**
   * {@inheritdoc}
   */
  public function getCc() {
    return $this->get('cc');
  }

  /**
   * {@inheritdoc}
   */
  public function getBcc() {
    return $this->get('bcc');
  }

  /**
   * {@inheritdoc}
   */
  public function getFrom() {
    return $this->get('from');
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo() {
    return $this->get('replyto');
  }

  /**
   * {@inheritdoc}
   */
  public function sendToAuthor() {
    return $this->get('author');
  }

  /**
   * {@inheritdoc}
   */
  public function getAbort() {
    return $this->get('abort');
  }

  /**
   * {@inheritdoc}
   */
  public function getDebug() {
    return $this->get('debug');
  }

}
