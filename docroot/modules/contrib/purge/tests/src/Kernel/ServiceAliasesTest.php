<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel;

use Drupal\purge\Logger\LoggerChannelPartFactory;
use Drupal\purge_autowire_test\AutowiredConsumer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that every purge service can be autowired through its interface.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class ServiceAliasesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['purge', 'purge_autowire_test'];

  /**
   * Maps the interface-aliased constructor properties to string service IDs.
   *
   * @var string[]
   */
  protected array $aliases = [
    'purgeLogger' => 'purge.logger',
    'purgeQueuers' => 'purge.queuers',
    'purgeInvalidationFactory' => 'purge.invalidation.factory',
    'purgeQueue' => 'purge.queue',
    'purgeQueueTxbuffer' => 'purge.queue.txbuffer',
    'purgeQueueStats' => 'purge.queue.stats',
    'purgeDiagnostics' => 'purge.diagnostics',
    'purgeProcessors' => 'purge.processors',
    'purgePurgers' => 'purge.purgers',
    'purgePurgersTrackerCapacity' => 'purge.purgers.tracker.capacity',
    'purgePurgersTrackerRuntimeMeasurement' => 'purge.purgers.tracker.runtime_measurement',
    'purgeTagsheaders' => 'purge.tagsheaders',
  ];

  /**
   * Tests that the interface aliases resolve to the canonical services.
   */
  public function testAutowiredAliasesResolveToCanonicalServices(): void {
    $consumer = $this->container->get('purge_autowire_test.consumer');
    $this->assertInstanceOf(AutowiredConsumer::class, $consumer);
    foreach ($this->aliases as $property => $service_id) {
      /** @var object $canonical */
      $canonical = $this->container->get($service_id);
      $this->assertSame(
        $canonical,
        $consumer->{$property},
        "Alias for '$service_id' must resolve to the same instance.",
      );
    }
    // The parts factory is private, so it cannot be fetched by ID.
    $this->assertInstanceOf(LoggerChannelPartFactory::class, $consumer->purgeLoggerPartsFactory);
  }

}
