<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines a content moderation notification interface.
 */
interface ContentModerationNotificationInterface extends ConfigEntityInterface {

  /**
   * Get the TO email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getTo();

  /**
   * Get the CC email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getCc();

  /**
   * Get the BCC email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getBcc();

  /**
   * Get the FROM email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getFrom();

  /**
   * Get the REPLY-TO email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getReplyTo();

  /**
   * Send the notification to the entity author.
   *
   * @return bool
   *   Returns TRUE if the notification should be sent to the entity author.
   */
  public function sendToAuthor();

  /**
   * Gets the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId();

  /**
   * Gets the relevant roles for this notification.
   *
   * @return string[]
   *   The role IDs that should receive notification.
   */
  public function getRoleIds();

  /**
   * Get the transitions for which to send this notification.
   *
   * @return string[]
   *   The relevant transitions.
   */
  public function getTransitions();

  /**
   * Gets the notification subject.
   *
   * @return string
   *   The message subject.
   */
  public function getSubject();

  /**
   * Gets the message value.
   *
   * @return string
   *   The message body text.
   */
  public function getMessage();

  /**
   * Gets the message format.
   *
   * @return string
   *   The format to be used for the message body.
   */
  public function getMessageFormat();

  /**
   * Get the Debug state.
   *
   * @return string
   *   The debug status of the notification.
   */
  public function getDebug();

  /**
   * Get the Abort-Sending email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for aborting the notification.
   */
  public function getAbort();

}
