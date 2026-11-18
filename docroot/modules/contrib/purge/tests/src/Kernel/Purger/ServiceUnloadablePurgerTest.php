<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\Purger;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\purge\Kernel\KernelServiceTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;

/**
 * Tests the purge.purgers service when a purger cannot be created.
 *
 * This is the state during a configuration import that uninstalls a module
 * providing a purger: the purger is still configured and its definition is
 * still discovered, but a service it needs is already gone.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class ServiceUnloadablePurgerTest extends KernelServiceTestBase {

  /**
   * The name of the service as defined in services.yml.
   *
   * @var string
   */
  protected $serviceId = 'purge.purgers';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'purge_purger_test',
    'purge_purger_unloadable_test',
  ];

  /**
   * Messages logged on the 'purge' logger channel, in order.
   *
   * @var array[]
   */
  protected array $logged = [];

  /**
   * Set up the test.
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);

    // Record what the purgers service logs, so the context can be inspected.
    $this->initializeLoggerService();
    $this->purgeLogger->setChannel('purgers', [RfcLogLevel::ERROR]);
    $logger = new class($this->logged) implements LoggerInterface {
      use RfcLoggerTrait;

      public function __construct(protected array &$logged) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->logged[] = ['message' => (string) $message, 'context' => $context];
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);

    // Configure the purgers directly: setPluginsEnabled() would validate them.
    $this->config('purge.plugins')->set('purgers', [
      ['order_index' => 1, 'instance_id' => 'id0', 'plugin_id' => 'good'],
      ['order_index' => 2, 'instance_id' => 'id1', 'plugin_id' => 'unloadable'],
    ])->save();
    $this->initializeService();
    $this->service->reload();
  }

  /**
   * Tests that a purger that fails to load is left out everywhere.
   */
  public function testUnloadablePurgerIsSkipped(): void {
    $this->assertSame(['id0' => 'Good Purger'], array_map('strval', $this->service->getLabels()));
    $this->assertSame(['id0' => 'good'], $this->service->getPluginsEnabled());
    $this->assertSame(['id0'], array_keys($this->service->getTypesByPurger()));
    $this->assertContains('tag', $this->service->getTypes());

    // Removing the unloadable purger must not try to call ::delete() on it.
    $this->service->setPluginsEnabled(['id0' => 'good']);
    $this->assertSame(['id0' => 'Good Purger'], array_map('strval', $this->service->getLabels()));
  }

  /**
   * Tests that the load error is logged with a message, not the exception.
   */
  public function testUnloadablePurgerErrorIsLoggedAsString(): void {
    $this->service->getLabels();
    $errors = array_values(array_filter($this->logged, fn($entry) => str_contains($entry['message'], 'Error loading purger')));
    $this->assertCount(1, $errors);
    $context = $errors[0]['context'];
    $this->assertSame('id1', $context['@id']);
    $this->assertSame('unloadable', $context['@plugin_id']);
    $this->assertIsString($context['@error']);
    $this->assertStringContainsString('purge_purger_unloadable_test.missing_service', $context['@error']);
  }

}
