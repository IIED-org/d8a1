<?php

declare(strict_types=1);

namespace Drupal\purge\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Sanitize plugin for 'drush sql:sanitize' which empties the purge queue.
 *
 * The queue is environment specific: it holds invalidations meant for the
 * site the database was exported from, usually production. When that database
 * is copied to another environment those items would be executed against the
 * wrong site (or against production, if purger credentials were copied along)
 * and can leave the queue in a state that the processors keep tripping over.
 * Emptying it as part of the sanitization run prevents both.
 *
 * This deliberately does not implement Drush's SanitizePluginInterface: it is
 * only a marker (Drush wires plugins through the hooks below) and it lives in
 * a different namespace on Drush 12 than on Drush 13, both of which this
 * module supports. The method signatures match the interface.
 */
final class SqlSanitizeCommands extends DrushCommands {

  /**
   * The 'purge.queue' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface
   */
  protected $purgeQueue;

  /**
   * The 'purge.queue.stats' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface
   */
  protected $purgeQueueStats;

  /**
   * Construct a SqlSanitizeCommands object.
   *
   * @param \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $purge_queue
   *   The purge queue service.
   * @param \Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface $purge_queue_stats
   *   The purge queue statistics service.
   */
  public function __construct(QueueServiceInterface $purge_queue, StatsTrackerInterface $purge_queue_stats) {
    parent::__construct();
    $this->purgeQueue = $purge_queue;
    $this->purgeQueueStats = $purge_queue_stats;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('purge.queue'),
      $container->get('purge.queue.stats')
    );
  }

  /**
   * Empty the purge queue.
   *
   * @hook post-command sql:sanitize
   *
   * @inheritdoc
   */
  public function sanitize($result, CommandData $commandData) {
    $total = (int) $this->purgeQueueStats->numberOfItems()->get();
    $this->purgeQueue->emptyQueue();
    $this->io()->success(
      dt('Emptied the purge queue (@total items).', ['@total' => $total])
    );
  }

  /**
   * Announce what sanitize() is going to do.
   *
   * @hook on-event sql-sanitize-confirms
   *
   * @inheritdoc
   */
  public function messages(&$messages, InputInterface $input) {
    $messages[] = dt('Empty the purge queue.');
  }

}
