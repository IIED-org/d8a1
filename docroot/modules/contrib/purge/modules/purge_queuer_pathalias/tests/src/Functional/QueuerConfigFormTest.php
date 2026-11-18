<?php

namespace Drupal\Tests\purge_queuer_pathalias\Functional;

use Drupal\purge_queuer_pathalias\Form\ConfigurationForm;
use Drupal\Tests\purge_ui\FunctionalJavascript\Form\Config\QueuerConfigFormTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge_queuer_pathalias\Form\ConfigurationForm.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class QueuerConfigFormTest extends QueuerConfigFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['purge_queuer_pathalias'];

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'pathalias';

  /**
   * {@inheritdoc}
   */
  protected $formClass = ConfigurationForm::class;

  /**
   * {@inheritdoc}
   */
  protected $formId = 'purge_queuer_pathalias.configuration_form';

  /**
   * {@inheritdoc}
   */
  public function testDefaultFormState(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->getPath());
    $this->assertSession()->fieldExists('edit-invalidation-type');
    $this->assertSession()->fieldExists('edit-base-urls');
    $this->assertSession()->fieldExists('edit-max-per-request');
    $this->assertSession()->checkboxChecked('edit-queue-insert');
    $this->assertSession()->checkboxChecked('edit-queue-update');
    $this->assertSession()->checkboxChecked('edit-queue-delete');
    $this->assertSession()->checkboxChecked('edit-expand-undefined-langcode');
    $this->assertSession()->checkboxChecked('edit-skip-syncing');
    $this->assertSession()->pageTextContains('Invalidation type');
    // No purgers are enabled, so the missing type support gets flagged.
    $this->assertSession()->pageTextContains('None of your enabled purgers support URL or path invalidation');
  }

  /**
   * {@inheritdoc}
   */
  public function testSaveConfigurationSubmit(): void {
    $this->drupalLogin($this->adminUser);

    // Submitting valid values stores them.
    $form_state = $this->getFormStateInstance();
    $form_state->addBuildInfo('args', $this->formArgs);
    $form_state->setValue('invalidation_type', 'path');
    $form_state->setValue('base_urls', " https://cdn.example.com/ \nhttps://www.example.com\n\n");
    $form_state->setValue('queue_insert', 1);
    $form_state->setValue('queue_update', 1);
    // Programmatically unchecked checkboxes are submitted as NULL: any other
    // value - including 0 - counts as checked.
    $form_state->setValue('queue_delete', NULL);
    $form_state->setValue('expand_undefined_langcode', NULL);
    $form_state->setValue('skip_syncing', 1);
    $form_state->setValue('max_per_request', '25');
    $this->formBuilder()->submitForm($this->getFormInstance(), $form_state);
    $this->assertCount(0, $form_state->getErrors());
    $config = $this->config('purge_queuer_pathalias.settings');
    $this->assertSame('path', $config->get('invalidation_type'));
    $this->assertSame([
      'https://cdn.example.com',
      'https://www.example.com',
    ], $config->get('base_urls'));
    $this->assertFalse($config->get('queue_delete'));
    $this->assertFalse($config->get('expand_undefined_langcode'));
    $this->assertTrue($config->get('skip_syncing'));
    $this->assertSame(25, $config->get('max_per_request'));

    // An invalid base URL results in exactly one validation error.
    $form_state = $this->getFormStateInstance();
    $form_state->addBuildInfo('args', $this->formArgs);
    $form_state->setValue('invalidation_type', 'auto');
    $form_state->setValue('base_urls', "https://www.example.com\nnot a url");
    $form_state->setValue('max_per_request', '25');
    $this->formBuilder()->submitForm($this->getFormInstance(), $form_state);
    $this->assertCount(1, $form_state->getErrors());
    $this->assertSame('path', $this->config('purge_queuer_pathalias.settings')
      ->get('invalidation_type'));

    // An invalidation type outside of the offered options gets rejected.
    $form_state = $this->getFormStateInstance();
    $form_state->addBuildInfo('args', $this->formArgs);
    $form_state->setValue('invalidation_type', 'tag');
    $form_state->setValue('base_urls', '');
    $form_state->setValue('max_per_request', '25');
    $this->formBuilder()->submitForm($this->getFormInstance(), $form_state);
    $errors = $form_state->getErrors();
    $this->assertCount(1, $errors);
    $this->assertArrayHasKey('invalidation_type', $errors);
    $this->assertSame('path', $this->config('purge_queuer_pathalias.settings')
      ->get('invalidation_type'));
  }

}
