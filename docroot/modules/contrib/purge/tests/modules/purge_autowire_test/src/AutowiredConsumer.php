<?php

declare(strict_types=1);

namespace Drupal\purge_autowire_test;

use Drupal\purge\Logger\LoggerChannelPartFactoryInterface;
use Drupal\purge\Logger\LoggerServiceInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticsServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\CapacityTrackerInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\RuntimeMeasurementTrackerInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface;
use Drupal\purge\Plugin\Purge\Queue\TxBufferInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeadersServiceInterface;

/**
 * Consumes every purge service through its interface alias.
 *
 * The service definition carries no arguments; the container resolves each
 * constructor parameter from the interface aliases in purge.services.yml.
 */
final class AutowiredConsumer {

  /**
   * Constructs the autowired consumer.
   */
  public function __construct(
    public readonly LoggerServiceInterface $purgeLogger,
    public readonly LoggerChannelPartFactoryInterface $purgeLoggerPartsFactory,
    public readonly QueuersServiceInterface $purgeQueuers,
    public readonly InvalidationsServiceInterface $purgeInvalidationFactory,
    public readonly QueueServiceInterface $purgeQueue,
    public readonly TxBufferInterface $purgeQueueTxbuffer,
    public readonly StatsTrackerInterface $purgeQueueStats,
    public readonly DiagnosticsServiceInterface $purgeDiagnostics,
    public readonly ProcessorsServiceInterface $purgeProcessors,
    public readonly PurgersServiceInterface $purgePurgers,
    public readonly CapacityTrackerInterface $purgePurgersTrackerCapacity,
    public readonly RuntimeMeasurementTrackerInterface $purgePurgersTrackerRuntimeMeasurement,
    public readonly TagsHeadersServiceInterface $purgeTagsheaders,
  ) {
  }

}
