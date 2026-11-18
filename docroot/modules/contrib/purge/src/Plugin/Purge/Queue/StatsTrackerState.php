<?php

declare(strict_types=1);

namespace Drupal\purge\Plugin\Purge\Queue;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\State\StateInterface;

/**
 * Exposes the queue statistics storage through the state API.
 *
 * Only provides the deprecated StatsTracker::$state property.
 *
 * @internal
 *
 * @deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Use
 *   StatsTracker::$storage instead.
 *
 * @see https://www.drupal.org/project/purge/issues/3635585
 */
final class StatsTrackerState implements StateInterface {

  /**
   * Constructs a StatsTrackerState object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $storage
   *   The key-value store that holds the statistics.
   */
  public function __construct(
    protected KeyValueStoreInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL): mixed {
    $this->triggerDeprecation();
    return $this->storage->get($key, $default);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys): array {
    $this->triggerDeprecation();
    return $this->storage->getMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->triggerDeprecation();
    $this->storage->set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data) {
    $this->triggerDeprecation();
    $this->storage->setMultiple($data);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $this->triggerDeprecation();
    $this->storage->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys) {
    $this->triggerDeprecation();
    $this->storage->deleteMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    $this->triggerDeprecation();
    // The key-value store has no static cache.
  }

  /**
   * {@inheritdoc}
   */
  public function getValuesSetDuringRequest(string $key): ?array {
    $this->triggerDeprecation();
    // Changes are not tracked.
    return NULL;
  }

  /**
   * Triggers the deprecation of the StatsTracker::$state property.
   */
  private function triggerDeprecation(): void {
    @trigger_error('The ' . StatsTracker::class . '::$state property is deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Use the $storage property instead. See https://www.drupal.org/project/purge/issues/3635585', E_USER_DEPRECATED);
  }

}
