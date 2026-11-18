<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\DiagnosticCheck;

use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\PluginManager;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'queue_size' diagnostic check and its purge.dangerous override.
 *
 * @see \Drupal\purge\Plugin\Purge\DiagnosticCheck\QueueSizeDiagnosticCheck
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class QueueSizeDiagnosticCheckTest extends KernelTestBase {

  /**
   * Instantiate a fresh 'queue_size' check with the given queue volume.
   *
   * @param int $number_of_items
   *   The number of items the queue statistics should report.
   *
   * @return \Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface
   *   The check, which has not run yet.
   */
  protected function createCheck($number_of_items) {
    $this->container->get('purge.queue.stats')
      ->numberOfItems()
      ->set($number_of_items);
    // Fresh instances are required as checks cache their outcome after run().
    // The plugin manager service is private, so instantiate it directly.
    $plugin_manager = new PluginManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler')
    );
    return $plugin_manager->createInstance('queue_size');
  }

  /**
   * Tests the severity thresholds at 30,000 and 100,000 items.
   */
  public function testThresholds(): void {
    $check = $this->createCheck(0);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_OK, $check->getSeverity());
    $this->assertSame('Your queue is empty!', (string) $check->getRecommendation());

    $check = $this->createCheck(29999);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_OK, $check->getSeverity());
    $this->assertSame(29999, $check->getValue());

    $check = $this->createCheck(30000);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_WARNING, $check->getSeverity());
    $this->assertStringContainsString('more then 30,000 items', (string) $check->getRecommendation());

    $check = $this->createCheck(99999);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_WARNING, $check->getSeverity());

    $check = $this->createCheck(100000);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_ERROR, $check->getSeverity());
    $recommendation = (string) $check->getRecommendation();
    $this->assertStringContainsString('exceeded 100,000 items', $recommendation);
    $this->assertStringContainsString('shut down cache invalidation', $recommendation);
    $this->assertStringContainsString('drush state:set purge.dangerous 1', $recommendation);
  }

  /**
   * Tests that the purge.dangerous state flag lifts the safety shutdown.
   */
  public function testDangerousStateFlag(): void {
    $state = $this->container->get('state');

    // Without the flag, exceeding 100,000 items blocks purging.
    $check = $this->createCheck(250000);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_ERROR, $check->getSeverity());

    // With the flag, the check keeps warning but no longer blocks purging.
    $state->set('purge.dangerous', TRUE);
    $check = $this->createCheck(250000);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_WARNING, $check->getSeverity());
    $recommendation = (string) $check->getRecommendation();
    $this->assertStringContainsString('exceeded 100,000 items', $recommendation);
    $this->assertStringContainsString('purge.dangerous state flag is set', $recommendation);
    $this->assertStringNotContainsString('shut down cache invalidation', $recommendation);

    // The flag has no effect below the 100,000 item threshold.
    $check = $this->createCheck(50000);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_WARNING, $check->getSeverity());
    $check = $this->createCheck(10);
    $this->assertSame(DiagnosticCheckInterface::SEVERITY_OK, $check->getSeverity());
  }

}
