<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\URL;
use Drupal\Core\Utility\Token;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Render\Markup;

/**
 * General service for moderation-related questions about Entity API.
 */
class Notification implements NotificationInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The notification information service.
   *
   * @var \Drupal\content_moderation_notifications\NotificationInformationInterface
   */
  protected $notificationInformation;

  /**
   * The token entity mapper, if available.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * Creates a new ModerationInformation instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\content_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
   *   The token entity mapper service.
   * @param Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, ModuleHandlerInterface $module_handler, NotificationInformationInterface $notification_information, ConfigFactoryInterface $config_factory, TokenEntityMapperInterface $token_entity_mapper = NULL, Token $token = null) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->moduleHandler = $module_handler;
    $this->notificationInformation = $notification_information;
    $this->configFactory = $config_factory;
    $this->tokenEntityMapper = $token_entity_mapper;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(EntityInterface $entity) {
    $notifications = $this->notificationInformation->getNotifications($entity);
    if (!empty($notifications)) {
      $this->sendNotification($entity, $notifications);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendNotification(EntityInterface $entity, array $notifications) {
    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    foreach ($notifications as $notification) {
      $email_roles = [];
      $email_to = [];
      $email_cc = [];
      $email_bcc = [];
      $email_from = '';
      $email_replyto = '';
      $email_subject = '';
      $email_message = [];
      $email_abort = $notification->getAbort();
      $email_abort_flag = FALSE;
      $debug = $notification->getDebug();
      $email_site = $this->configFactory->get('system.site')->get('mail');

      $data['langcode'] = $this->currentUser->getPreferredLangcode();
      $data['notification'] = $notification;

      // Setup the email subject and body content.
      // Add the entity and related fields as context to aid in token and Twig
      // replacement.
      $data['params']['context'] = [
        'entity' => $entity,
        'user' => $this->currentUser,
        'title' => $entity->label(),
        'label' => $entity->label(),
        'notification' => $notification,
        'base_url' => Url::fromRoute('<front>')->setAbsolute()->toString(),
        'site_email' => $email_site,
        'site_name' => $this->configFactory->get('system.site')->get('name'),
        'site_slogan' => $this->configFactory->get('system.site')->get('slogan'),
        'site_front' => $this->configFactory->get('system.site')->get('front'),
        'workflow' => $this->notificationInformation->getWorkflow($entity)->label(),
        'workflow_id' => $this->notificationInformation->getWorkflow($entity)->id(),
        'transition' => $this->notificationInformation->getTransition($entity)->label(),
        'transition_id' => $this->notificationInformation->getTransition($entity)->id(),
        'previous_state' => $this->notificationInformation->getPreviousState($entity)->label(),
        'previous_state_id' => $this->notificationInformation->getPreviousState($entity)->id(),
        // @todo: make 'state' the label, not the id.
        'state' => $entity->moderation_state->value,
        'state_id' => $entity->moderation_state->value,
        // Aliases of state and state_id.
        // @todo: make 'new_state' the label, not the id.
        'new_state' => $entity->moderation_state->value,
        'new_state_id' => $entity->moderation_state->value,
        'entity_type' => $entity->getEntityTypeId(),
      ];

      // Add additional context for entity type or token.
      if ($this->tokenEntityMapper) {
        $data['params']['context'][$this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId())] = $entity;
      }
      else {
        $data['params']['context'][$entity->getEntityTypeId()] = $entity;
      }

      // Get Roles.
      foreach ($notification->getRoleIds() as $role) {
        /** @var \Drupal\user\UserInterface[] $role_users */
        $role_users = $this->entityTypeManager
          ->getStorage('user')
          ->loadByProperties(['roles' => $role]);
        foreach ($role_users as $role_user) {
          $email_roles[] = $role_user->getEmail();
        }
      }

      // Get TO email addresses and process any Twig templating.
      $email_to_template = $notification->getTo();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_to_template,
        '#context' => $data['params']['context'],
      );
      $email_to = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens.
      if ($this->token) {
        $email_to = $this->token->replace($email_to, $data['params']['context'], ['clear' => TRUE]);
      }
      // Split emails on commas and newlines and save as array.
      $email_to_array = array_map('trim', preg_split('/(,|\n)/', $email_to));
      // Add Author to TO header if "Email the Author" is checked.
      if ($notification->author and ($entity instanceof EntityOwnerInterface)) {
        $email_to_array[] = $entity->getOwner()->getEmail();
      }
      // Remove any null values, remove duplicates, and create comma-separated
      // string.
      $email_to = implode(',', array_unique(array_filter($email_to_array)));

      // Get CC email addresses and process any Twig templating.
      $email_cc_template = $notification->getCc();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_cc_template,
        '#context' => $data['params']['context'],
      );
      $email_cc = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens.
      if ($this->token) {
        $email_cc = $this->token->replace($email_cc, $data['params']['context'], ['clear' => TRUE]);
      }
      // Split emails on commas and newlines and save as array.
      $email_cc_array = array_map('trim', preg_split('/(,|\n)/', $email_cc));
      // Remove any null values, remove duplicates, and create comma-separated
      // string.
      $email_cc = implode(',', array_unique(array_filter($email_cc_array)));

      // Get BCC email addresses and process any Twig templating.
      $email_bcc_template = $notification->getBcc();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_bcc_template,
        '#context' => $data['params']['context'],
      );
      $email_bcc = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens.
      if ($this->token) {
        $email_bcc = $this->token->replace($email_bcc, $data['params']['context'], ['clear' => TRUE]);
      }
      // Split emails on commas and newlines and save as array.
      $email_bcc_array = array_map('trim', preg_split('/(,|\n)/', $email_bcc));
      // Remove any null values, remove duplicates, and create comma-separated
      // string.
      $email_bcc = implode(',', array_unique(array_filter($email_bcc_array)));

      // If Role(s) are selected, add them to the BCC header.
      if ($email_roles) {
        $email_bcc .= ',' . implode(',', $email_roles);
      }

      // Get FROM email addresses and process any Twig templating.
      $email_from_template = $notification->getFrom();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_from_template,
        '#context' => $data['params']['context'],
      );
      $email_from = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens.
      if ($this->token) {
        $email_from = $this->token->replace($email_from, $data['params']['context'], ['clear' => TRUE]);
      }
      // Only a single FROM address allowed, so don't perform the same
      // email array cleaning we do with TO, CC, and BCC.
      $email_from = trim($email_from);
      // Use site email address if not specified.
      if (empty($email_from)) {
        $email_from = $email_site;
      }
      // Replace Token module tokens.
      if ($this->token) {
        $email_from = $this->token->replace($email_from, $data['params']['context'], ['clear' => TRUE]);
      }


      // Get FROM email addresses and process any Twig templating.
      $email_replyto_template = $notification->getReplyTo();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_replyto_template,
        '#context' => $data['params']['context'],
      );
      $email_replyto = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens.
      if ($this->token) {
        $email_replyto = $this->token->replace($email_replyto, $data['params']['context'], ['clear' => TRUE]);
      }
      $email_replyto = trim($email_replyto);
      // Use site email address if not specified.
      if (empty($email_replyto)) {
        $email_replyto = $email_site;
      }

      // Get Subject and process any Twig templating.
      $email_subject_template = $notification->getSubject();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_subject_template,
        '#context' => $data['params']['context'],
      );
      $email_subject = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens
      if ($this->token) {
        $email_subject = $this->token->replace($email_subject, $data['params']['context'], ['clear' => TRUE]);
      }
      // Remove any newlines from Subject.
      $email_subject = trim(str_replace(["\r", "\n"], ' ', $email_subject));

      // Get Message, process any Twig templating, and apply input filter.
      $email_message_template = $notification->getMessage();
      $template = array(
        '#type' => 'inline_template',
        '#template' => $email_message_template,
        '#context' => $data['params']['context'],
      );
      $email_message = \Drupal::service('renderer')->renderPlain($template);
      // Replace Token module tokens
      if ($this->token) {
        $email_message = $this->token->replace($email_message, $data['params']['context'], ['clear' => TRUE]);
      }
      $email_message = check_markup($email_message, $notification->getMessageFormat());


      // Abort if the Abort email address is present in To, CC, BCC, From or
      // ReplyTo.
      $all_emails = array_merge($email_to_array, $email_cc_array, $email_bcc_array, [
        $email_from,
        $email_replyto,
      ]);
      if (!empty($email_abort) && in_array($email_abort, $all_emails)) {
        $email_abort_flag = TRUE;
      }

      // Build data array to send to mail()
      $data['params']['to'] = $email_to;
      $data['params']['from'] = $email_from;
      $data['params']['headers']['cc'] = $email_cc;
      $data['params']['headers']['Content-Type'] = 'text/plain; charset=UTF-8';
      $data['params']['cc'] = $email_cc;
      $data['params']['headers']['bcc'] = $email_bcc;
      $data['params']['bcc'] = $email_bcc;
      $data['params']['replyto'] = $email_replyto;
      $data['params']['subject'] = $email_subject;
      $data['params']['body'][] = $email_message;
      $data['params']['abort'] = $email_abort;
      $data['params']['abort_flag'] = $email_abort_flag;
      $data['params']['send'] = $email_abort_flag;

      // Let other modules to alter the email data.
      $this->moduleHandler->alter('content_moderation_notification_mail_data', $entity, $data);

      // Verify if we have any recipients.
      if (empty($data['params']['to']) && empty($data['params']['headers']['cc']) && empty($data['params']['headers']['bcc'])) {
        $no_recipients = TRUE;
      }
      else {
        $no_recipients = FALSE;
      }

      if ($debug) {
        drupal_set_message("Notification (ID):  {$notification->label} ({$notification->id()})");
        drupal_set_message("To Template: " . $this->crnl($email_to_template));
        drupal_set_message("To: " . $this->crnl($data['params']['to']));
        drupal_set_message("Cc Template: " . $this->crnl($email_cc_template));
        drupal_set_message("Cc: " . $this->crnl($data['params']['headers']['cc']));
        drupal_set_message("Bcc Template: " . $this->crnl($email_bcc_template));
        drupal_set_message("Bcc: " . $this->crnl($data['params']['headers']['bcc']));
        drupal_set_message("From Template: " . $this->crnl($email_from_template));
        drupal_set_message("From: " . $this->crnl($data['params']['from']));
        drupal_set_message("Reply-To Template: " . $this->crnl($email_replyto_template));
        drupal_set_message("Reply-To: " . $this->crnl($data['params']['replyto']));
        drupal_set_message("Subject Template: " . $this->crnl($email_subject_template));
        drupal_set_message("Subject: " . $this->crnl($data['params']['subject']));
        drupal_set_message("Message Template: " . $this->crnl($email_message_template));
        drupal_set_message("Message: " . $this->crnl(implode('|', $data['params']['body'])));
        drupal_set_message("Abort: " . $this->crnl($data['params']['abort']));
        drupal_set_message("Abort Email Found?: " . ($data['params']['abort_flag'] ? 'Yes' : 'No'));
        drupal_set_message("LangCode: " . $this->crnl($data['langcode']));
        if ($no_recipients) {
          drupal_set_message("This notification was NOT sent because there are no recipients (TO, CC or BCC).");
        }
        if ($data['params']['abort_flag']) {
          drupal_set_message("This notification was NOT sent because the abort email address was found as a recipient.");
        }
        drupal_set_message("This notification was NOT sent because debugging is enabled.  Turn off debugging to send notifications and hide this information.");
      }
      else {
        if (!$no_recipients && !$data['params']['abort_flag']) {
          // Send message if there are recipients and no abort email address.
          $this->mailManager->mail('content_moderation_notifications', $notification->id(), $data['params']['to'], $data['langcode'], $data['params'], $data['params']['replyto'], !$data['params']['abort_flag']);
        }
      }
    }
  }

  /**
   * Change carriage returns and linefeeds into text equivalents \n and \r.
   *
   * @var string original string
   *
   * @returns string encoded string
   */
  private function crnl($str) {
    return str_replace(["\n", "\r"], ['\n', '\r'], $str);
  }

}
