<?php

namespace Drupal\purge_queue_test\Plugin\Purge\Queue;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueue;
use Drupal\purge\Plugin\Purge\Queue\MemoryQueue;
use Drupal\purge\Plugin\Purge\Queue\QueueInterface;

/**
 * A QueueInterface compliant memory queue.
 */
#[PurgeQueue(
  id: "b",
  label: new TranslatableMarkup("Memqueue B"),
  description: new TranslatableMarkup("A volatile and non-persistent memory queue")
)]
class BQueue extends MemoryQueue implements QueueInterface {}
