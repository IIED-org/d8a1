<?php

namespace Drupal\Tests\purge_ui\FunctionalJavascript;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge_ui\Controller\DashboardController::buildLoggingSection().
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class DashboardLoggingTest extends DashboardTestBase {

  /**
   * Test the logging section of the configuration form.
   *
   * @see \Drupal\purge_ui\Controller\DashboardController::buildLoggingSection
   */
  public function testLoggingSection(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->route);
    $this->assertSession()->responseContains('Logging');
    $this->assertSession()->responseContains('Configure logging behavior');
    $this->assertSession()->linkByHrefExists(Url::fromRoute('purge_ui.logging_config_form')->toString());
  }

}
