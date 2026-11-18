<?php

/**
 * @file
 * Post update functions for Purge.
 */

declare(strict_types=1);

use Drupal\purge\Plugin\Purge\Queue\StatsTracker;

/**
 * Force a cache refresh because new services were added.
 */
function purge_post_update_refresh_container(&$sandbox) {
  // Empty post-update hook.
}

/**
 * Force a cache refresh because service interface aliases were added.
 */
function purge_post_update_service_interface_aliases(&$sandbox) {
  // Empty post-update hook.
}

/**
 * Move the queue statistics from state to key-value storage.
 */
function purge_post_update_move_queue_stats_to_key_value(): void {
  $keys = [
    'purge_queue_number_of_items',
    'purge_queue_total_processing',
    'purge_queue_total_succeeded',
    'purge_queue_total_failed',
    'purge_queue_total_not_supported',
  ];
  $state = \Drupal::state();
  $values = array_filter($state->getMultiple($keys), fn ($value) => $value !== NULL);
  \Drupal::keyValue(StatsTracker::COLLECTION)->setMultiple($values);
  $state->deleteMultiple($keys);
}
