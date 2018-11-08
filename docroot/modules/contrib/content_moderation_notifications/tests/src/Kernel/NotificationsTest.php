<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\KernelTests\KernelTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Test sending of notifications for moderation state changes.
 *
 * @group content_moderation_notifications
 */
class NotificationsTest extends KernelTestBase {

  use AssertMailTrait;
  use ContentModerationNotificationCreateTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'content_moderation_notifications',
    'content_moderation_notifications_test',
    'entity_test',
    'filter',
    'filter_test',
    'system',
    'user',
    'workflows',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installConfig(['content_moderation', 'filter_test']);

    // Setup site email.
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    // Attach workflow to entity test.
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_rev', 'entity_test_rev');
    $workflow->save();
  }

  /**
   * Test sending of emails.
   */
  public function testEmailDelivery() {
    // @todo: Re-enable test
    // No emails should be sent for content without notifications.
    $entity = EntityTestRev::create();
    $entity->save();
    $this->assertEmpty($this->getMails());

    // Add a notification.
    $notification = $this->createNotification([
      // Test literal, Twig, and Token email addresses and various separators.
      'to' => " foo@example.com, {{ 'bar@example.com'|upper }}\n [site:mail] ",
      'cc' => " foo-cc@example.com, {{ 'bar-cc@example.com'|upper }}\n [site:mail] ",
      'bcc' => " foo-bcc@example.com, {{ 'bar-bcc@example.com'|upper }}\n [site:mail] ",
      // default to blank to test auto-setting
      'from' => '',
      'replyto' => " [site:mail] ",
      'subject' => ' [NOTICE] [site:mail] Testing {{ "Twig"|upper }} ',
      'abort' => 'abort@example.com',
      'debug' => FALSE,
      'transitions' => [
        'create_new_draft' => 'create_new_draft',
        'publish' => 'publish',
        'archived_published' => 'archived_published',
      ],
    ]);

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('draft', $entity->moderation_state->value);
    $entity->save();

    // Get data for displaying for test failures
    $emails = $this->getMails();
    $last_email = end($emails);

    $this->assertMail('to', 'foo@example.com,BAR@EXAMPLE.COM,admin@example.com', "Failed: Draft TO=[{$last_email['to']}]");
    $this->assertCcRecipients('foo-cc@example.com,BAR-CC@EXAMPLE.COM,admin@example.com', "Failed: Draft BCC={$last_email['headers']['cc']}");
    $this->assertBccRecipients('foo-bcc@example.com,BAR-BCC@EXAMPLE.COM,admin@example.com', "Failed: Draft BCC={$last_email['headers']['bcc']}");
    $this->assertMail('from', 'admin@example.com', "Failed: Draft FROM={$last_email['from']}");
    $this->assertMail('reply-to', 'admin@example.com', "Failed: Draft REPLY-TO=[{$last_email['reply-to']}]");
    $this->assertMail('id', 'content_moderation_notifications_example_notification', "Failed: Draft ID={$last_email['id']}");
    $this->assertMail('subject', '[NOTICE] admin@example.com Testing TWIG', "Failed: Draft SUBJECT={$last_email['subject']}");
    $this->assertCount(1, $this->getMails());

    $entity->moderation_state = 'published';
    $entity->save();

    // Get data for displaying for test failures
    $emails = $this->getMails();
    $last_email = end($emails);

    $this->assertMail('to', 'foo@example.com,BAR@EXAMPLE.COM,admin@example.com', "Failed: Published TO=[{$last_email['to']}]");
    $this->assertCcRecipients('foo-cc@example.com,BAR-CC@EXAMPLE.COM,admin@example.com', "Failed: Published BCC={$last_email['headers']['cc']}");
    $this->assertBccRecipients('foo-bcc@example.com,BAR-BCC@EXAMPLE.COM,admin@example.com', "Failed: Published BCC={$last_email['headers']['bcc']}");
    $this->assertMail('from', 'admin@example.com', "Failed: Published FROM={$last_email['from']}");
    $this->assertMail('reply-to', 'admin@example.com', "Failed: Published REPLY-TO=[{$last_email['reply-to']}]");
    $this->assertMail('id', 'content_moderation_notifications_example_notification', "Failed: Published ID={$last_email['id']}");
    $this->assertMail('subject', '[NOTICE] admin@example.com Testing TWIG', "Failed: Published SUBJECT={$last_email['subject']}");
    $this->assertCount(2, $this->getMails());

    // No mail should be sent for irrelevant transition.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('published', $entity->moderation_state->value);
    $entity->moderation_state = 'archived';
    $entity->save();
    $this->assertCount(2, $this->getMails());

    // Verify alter hook is functioning.
    // @see content_moderation_notifications_test_content_moderation_notification_mail_data_alter
    \Drupal::state()->set('content_moderation_notifications_test.alter', TRUE);
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('archived', $entity->moderation_state->value);
    $entity->moderation_state = 'published';
    $entity->save();

    // Get data for displaying for test failures
    $emails = $this->getMails();
    $last_email = end($emails);

    $this->assertMail('to', 'foo@example.com,BAR@EXAMPLE.COM,admin@example.com', "Failed: Alter TO=[{$last_email['to']}]");
    $this->assertCcRecipients('foo-cc@example.com,BAR-CC@EXAMPLE.COM,admin@example.com', "Failed: Alter BCC={$last_email['headers']['cc']}");
    $this->assertBccRecipients('foo-bcc@example.com,BAR-BCC@EXAMPLE.COM,admin@example.com', "Failed: Alter BCC={$last_email['headers']['bcc']}");
    $this->assertMail('from', 'admin@example.com', "Failed: Alter FROM={$last_email['from']}");
    $this->assertMail('reply-to', 'admin@example.com', "Failed: Alter REPLY-TO=[{$last_email['reply-to']}]");
    $this->assertMail('id', 'content_moderation_notifications_example_notification', "Failed: Alter ID={$last_email['id']}");
    $this->assertMail('subject', '[NOTICE] admin@example.com Testing TWIG', "Failed: Alter SUBJECT={$last_email['subject']}");

    $this->assertCount(3, $this->getMails());
  }

  /**
   * Helper method to assert the Cc recipients.
   *
   * @param string $recipients
   *   The expected recipients.
   */
  protected function assertCcRecipients($recipients) {
    $mails = $this->getMails();
    $mail = end($mails);
    $this->assertNotEmpty($mail['headers']['cc']);
    $this->assertEquals($recipients, $mail['headers']['cc']);
  }


  /**
   * Helper method to assert the Bcc recipients.
   *
   * @param string $recipients
   *   The expected recipients.
   */
  protected function assertBccRecipients($recipients) {
    $mails = $this->getMails();
    $mail = end($mails);
    $this->assertNotEmpty($mail['headers']['bcc']);
    $this->assertEquals($recipients, $mail['headers']['bcc']);
  }

}
