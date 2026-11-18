<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\DiagnosticCheck;

use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge\Plugin\Purge\DiagnosticCheck\MaxAgeCheck.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class MaxAgeCheckTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['purge', 'system'];

  /**
   * Retrieve a freshly instantiated 'maxage' check for the given TTL.
   *
   * @param int $max_age
   *   The value to store in system.performance:cache.page.max_age.
   *
   * @return \Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface
   *   The check, not yet run.
   */
  protected function createCheck(int $max_age): DiagnosticCheckInterface {
    $this->config('system.performance')
      ->set('cache.page.max_age', $max_age)
      ->save();
    $diagnostics = $this->container->get('purge.diagnostics');
    $diagnostics->reload();
    foreach ($diagnostics as $check) {
      if ($check->getPluginId() === 'maxage') {
        return $check;
      }
    }
    $this->fail('The maxage check was not found.');
  }

  /**
   * Tests the severity and value reported for a range of TTL settings.
   */
  public function testSeverityAndValue(): void {
    // Everything under 28 days still warns; 28, 30, 31 and 32 days do not.
    $expectations = [
      0 => [DiagnosticCheckInterface::SEVERITY_WARNING, 'no caching'],
      60 => [DiagnosticCheckInterface::SEVERITY_WARNING, '1 minute'],
      3600 => [DiagnosticCheckInterface::SEVERITY_WARNING, '1 hour'],
      86400 => [DiagnosticCheckInterface::SEVERITY_WARNING, '1 day'],
      604800 => [DiagnosticCheckInterface::SEVERITY_WARNING, '1 week'],
      // 27 days is still under a month.
      2332800 => [DiagnosticCheckInterface::SEVERITY_WARNING, '3.9 weeks'],
      // 28 days (the shortest month) is no longer under a month.
      2419200 => [DiagnosticCheckInterface::SEVERITY_OK, '1 month'],
      // 30 and 31 days.
      2592000 => [DiagnosticCheckInterface::SEVERITY_OK, '1 month'],
      2678400 => [DiagnosticCheckInterface::SEVERITY_OK, '1 month'],
      // The module's own "1 month" option on the performance form.
      2764800 => [DiagnosticCheckInterface::SEVERITY_OK, '1 month'],
      // The module's own "6 months" option on the performance form.
      16588800 => [DiagnosticCheckInterface::SEVERITY_OK, '6 months'],
      31536000 => [DiagnosticCheckInterface::SEVERITY_OK, '1 year'],
      63072000 => [DiagnosticCheckInterface::SEVERITY_OK, 'more than 1 year'],
    ];
    foreach ($expectations as $max_age => [$severity, $value]) {
      $check = $this->createCheck($max_age);
      $this->assertSame($severity, $check->getSeverity(), "Severity for max_age $max_age");
      $this->assertSame($value, (string) $check->getValue(), "Value for max_age $max_age");
    }
  }

  /**
   * Tests that the "under a month" recommendation stops at 28 days.
   */
  public function testMonthRecommendation(): void {
    $under = (string) $this->createCheck(2419199)->getRecommendation();
    $this->assertStringContainsString('under a month', $under);
    $month = (string) $this->createCheck(2419200)->getRecommendation();
    $this->assertStringNotContainsString('under a month', $month);
    $this->assertStringContainsString('over a year', $month);
  }

}
