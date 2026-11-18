<?php

declare(strict_types=1);

namespace Drupal\Tests\purge_ui\FunctionalJavascript\Form;

use Drupal\purge_ui\Form\PurgerInvalidateForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge_ui\Form\PurgerInvalidateForm.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class PurgerInvalidateFormTest extends AjaxFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['purge_ui', 'purge_purger_test'];

  /**
   * {@inheritdoc}
   */
  protected $formClass = PurgerInvalidateForm::class;

  /**
   * {@inheritdoc}
   */
  protected $route = 'purge_ui.purger_invalidate_form';

  /**
   * {@inheritdoc}
   */
  protected $routeParameters = ['id' => 'id0'];

  /**
   * {@inheritdoc}
   */
  protected $routeParametersInvalid = ['id' => 'doesnotexist'];

  /**
   * {@inheritdoc}
   */
  protected $routeTitle = 'What do you want to invalidate with Good Purger?';

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    $this->initializePurgersService(['good']);
    $this->initializeProcessorsService(['purge_ui_invalidate']);
  }

  /**
   * Opens the form in a modal dialog on the dashboard.
   */
  protected function openDialog(): void {
    $this->drupalLogin($this->adminUser);
    $this->visitDashboard();
    $this->assertSession()->linkExists('Good Purger');
    $url = $this->getPath();
    $js = <<<JS
    var ajaxSettings = {
      url: '{$url}',
      dialogType: 'modal',
      dialog: { width: 400 },
    };
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
    JS;
    $this->getSession()->executeScript($js);
    $this->assertSession()->waitForElement('css', '.ui-dialog');
    $this->assertSession()->pageTextContains('What do you want to invalidate with Good Purger?');
  }

  /**
   * Tests that the route 404s when the processor plugin is disabled.
   */
  public function testRouteRequiresProcessor(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->getPath());
    $this->assertSession()->pageTextNotContains('The requested page could not be found.');
    $this->initializeProcessorsService([], TRUE);
    $this->drupalGet($this->getPath());
    $this->assertSession()->pageTextContains('The requested page could not be found.');
  }

  /**
   * Tests basic expectations of the form.
   */
  public function testPresence(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->getPath());
    $this->assertSession()->pageTextContains('Every enabled purger that supports the selected type will process the invalidation immediately');
    $this->assertSession()->fieldExists('Type');
    $this->assertSession()->fieldExists('Expression');
    $this->assertSession()->buttonExists('Invalidate');
    $this->assertSession()->buttonExists('Cancel');
    // The good purger supports every type; 'everything' is selected by default
    // and takes no expression.
    $this->assertSession()->fieldValueEquals('Type', 'everything');
    $this->assertSession()->elementAttributeExists('css', 'textarea[name="expression"]', 'disabled');
    foreach (['tag', 'path', 'url', 'regex', 'domain', 'wildcardpath', 'wildcardurl'] as $type) {
      $this->assertSession()->optionExists('Type', $type);
    }
    // A purger with a smaller type set only offers what it supports.
    $this->initializePurgersService(['a']);
    $this->drupalGet($this->getPath());
    $this->assertSession()->optionExists('Type', 'everything');
    $this->assertSession()->optionNotExists('Type', 'tag');
  }

  /**
   * Tests that the "Cancel" button closes the dialog without purging.
   *
   * @see \Drupal\purge_ui\Form\PurgerInvalidateForm::buildForm
   * @see \Drupal\purge_ui\Form\CloseDialogTrait::closeDialog
   */
  public function testCancel(): void {
    $this->openDialog();
    $this->pressDialogButton('Cancel');
    $this->assertSession()->pageTextNotContains('What do you want to invalidate with Good Purger?');
    $this->assertSession()->pageTextNotContains('Successfully cleared');
  }

  /**
   * Tests invalidating 'everything', which requires no expression.
   *
   * @see \Drupal\purge_ui\Form\PurgerInvalidateForm::invalidatePurger
   */
  public function testInvalidateEverything(): void {
    $this->openDialog();
    $this->pressDialogButton('Invalidate');
    $this->assertSession()->waitForText('Successfully cleared everything.');
    $this->assertSession()->pageTextContains('Successfully cleared everything.');
    $this->assertSession()->linkExists('Good Purger');
  }

  /**
   * Tests invalidating multiple tags, one expression per line.
   *
   * @see \Drupal\purge_ui\Form\PurgerInvalidateForm::validateForm
   * @see \Drupal\purge_ui\Form\PurgerInvalidateForm::invalidatePurger
   */
  public function testInvalidateTags(): void {
    $this->openDialog();
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Type', 'tag');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('For example: node:1, menu:footer.');
    $this->assertSession()->elementAttributeNotExists('css', 'textarea[name="expression"]', 'disabled');
    $page->fillField('Expression', " node:1 \n\nmenu:footer\r\nnode:1");
    $this->pressDialogButton('Invalidate');
    $this->assertSession()->waitForText('Successfully cleared tag with expression "node:1".');
    $this->assertSession()->pageTextContains('Successfully cleared tag with expression "node:1".');
    $this->assertSession()->pageTextContains('Successfully cleared tag with expression "menu:footer".');
    $this->assertSession()->pageTextNotContains('Failed to clear');
  }

  /**
   * Tests that invalid expressions keep the dialog open with an error.
   *
   * @see \Drupal\purge_ui\Form\PurgerInvalidateForm::validateForm
   */
  public function testInvalidExpression(): void {
    $this->openDialog();
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Type', 'url');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('Expression', 'http://example.com/with space');
    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Invalidate');
    $this->assertSession()->waitForText('URL invalidations cannot contain spaces, use %20 instead.');
    $this->assertSession()->elementExists('css', '.ui-dialog');
    $this->assertSession()->pageTextNotContains('Successfully cleared');
  }

}
