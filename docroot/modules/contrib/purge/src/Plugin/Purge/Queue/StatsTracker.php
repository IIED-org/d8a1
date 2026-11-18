<?php

namespace Drupal\purge\Plugin\Purge\Queue;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\State\StateInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvStatesInterface;

/**
 * Provides the queue statistics tracker.
 */
class StatsTracker implements StatsTrackerInterface {

  /**
   * The key-value collection that stores the statistics.
   */
  const COLLECTION = 'purge.queue.stats';

  /**
   * Loaded statistical counters.
   *
   * @var \Drupal\purge\Counter\CounterInterface[]
   */
  protected $instances = [];

  /**
   * Current iterator position.
   *
   * @var int
   *
   * @ingroup iterator
   */
  protected $position = 0;

  /**
   * The key-value store that holds the statistics.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $storage;

  /**
   * The key-value store that holds the statistics, through the state API.
   *
   * @var \Drupal\Core\State\StateInterface
   *
   * @deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Use $storage
   *   instead.
   *
   * @see https://www.drupal.org/project/purge/issues/3635585
   */
  protected $state;

  /**
   * Buffer of counter values that need to be written back to storage.
   *
   * @var float[]
   */
  protected $stateBuffer = [];

  /**
   * Mapping of classes used for each counter.
   *
   * @var string[]
   */
  protected $statClasses = [
    self::NUMBER_OF_ITEMS     => NumberOfItemsStatistic::class,
    self::TOTAL_PROCESSING    => TotalProcessingStatistic::class,
    self::TOTAL_SUCCEEDED     => TotalSucceededStatistic::class,
    self::TOTAL_FAILED        => TotalFailedStatistic::class,
    self::TOTAL_NOT_SUPPORTED => TotalNotSupportedStatistic::class,
  ];

  /**
   * Non-associative but keyed layout of the statistical counters loaded.
   *
   * @var string[]
   */
  protected $stats = [
    self::NUMBER_OF_ITEMS     => 'purge_queue_number_of_items',
    self::TOTAL_PROCESSING    => 'purge_queue_total_processing',
    self::TOTAL_SUCCEEDED     => 'purge_queue_total_succeeded',
    self::TOTAL_FAILED        => 'purge_queue_total_failed',
    self::TOTAL_NOT_SUPPORTED => 'purge_queue_total_not_supported',
  ];

  /**
   * Construct a statistics tracker.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|\Drupal\Core\State\StateInterface $key_value_factory
   *   The key-value store factory. Passing the state service is deprecated.
   */
  public function __construct(KeyValueFactoryInterface|StateInterface $key_value_factory) {
    if ($key_value_factory instanceof StateInterface) {
      @trigger_error('Passing the state service to ' . __METHOD__ . '() is deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Pass the keyvalue service instead. See https://www.drupal.org/project/purge/issues/3635585', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $key_value_factory = \Drupal::service('keyvalue');
    }
    $this->storage = $key_value_factory->get(self::COLLECTION);
    // @phpstan-ignore property.deprecated, new.deprecatedClass, method.deprecatedClass
    $this->state = new StatsTrackerState($this->storage);
  }

  /**
   * Returns the key-value store that holds the statistics.
   *
   * Subclasses written for purge:8.x-3.7 and earlier may override the
   * constructor and set $this->state without calling the parent constructor,
   * in which case $this->storage is not set yet.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The key-value store.
   */
  private function getStorage(): KeyValueStoreInterface {
    if (!isset($this->storage)) {
      @trigger_error('Not calling ' . self::class . '::__construct() from a subclass constructor is deprecated in purge:8.x-3.8 and is required in purge:4.0.0. See https://www.drupal.org/project/purge/issues/3635585', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $this->storage = \Drupal::keyValue(self::COLLECTION);
    }
    return $this->storage;
  }

  /**
   * Initialize the counter instances.
   */
  protected function initializeStatistics() {
    if (!empty($this->instances)) {
      return;
    }

    // Fetch all statistic values from storage at once.
    $values = $this->getStorage()->getMultiple($this->stats);

    // Instantiate the persistent counters with the given values.
    foreach ($this->stats as $i => $statekey) {

      // Set a default as CounterInterface only understands integers.
      if ((!isset($values[$statekey])) || is_null($values[$statekey])) {
        $values[$statekey] = 0;
      }

      // Instantiate the counter and pass a write callback that puts written
      // values directly back into $this->stateBuffer. At the end of this
      // request, ::destruct() will pick them up and save the values.
      $this->instances[$i] = new $this->statClasses[$i]($values[$statekey]);
      $this->instances[$i]->setWriteCallback(
        function ($value) use ($statekey) {
          $this->stateBuffer[$statekey] = $value;
        }
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @ingroup countable
   */
  public function count(): int {
    $this->initializeStatistics();
    return count($this->instances);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    $this->initializeStatistics();
    return $this->instances[self::NUMBER_OF_ITEMS];
  }

  /**
   * {@inheritdoc}
   */
  public function totalFailed() {
    $this->initializeStatistics();
    return $this->instances[self::TOTAL_FAILED];
  }

  /**
   * {@inheritdoc}
   */
  public function totalProcessing() {
    $this->initializeStatistics();
    return $this->instances[self::TOTAL_PROCESSING];
  }

  /**
   * {@inheritdoc}
   */
  public function totalSucceeded() {
    $this->initializeStatistics();
    return $this->instances[self::TOTAL_SUCCEEDED];
  }

  /**
   * {@inheritdoc}
   */
  public function totalNotSupported() {
    $this->initializeStatistics();
    return $this->instances[self::TOTAL_NOT_SUPPORTED];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {

    // When the buffer contains changes, write them to storage in one go.
    if (count($this->stateBuffer)) {
      $this->getStorage()->setMultiple($this->stateBuffer);
      $this->stateBuffer = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetTotals() {
    $this->totalFailed()->set(0);
    $this->totalProcessing()->set(0);
    $this->totalSucceeded()->set(0);
    $this->totalNotSupported()->set(0);
  }

  /**
   * {@inheritdoc}
   */
  public function updateTotals(array $invalidations) {
    $changes = [
      'totalProcessing'   => 0,
      'totalSucceeded'    => 0,
      'totalFailed'       => 0,
      'totalNotSupported' => 0,
    ];
    foreach ($invalidations as $invalidation) {
      if ($invalidation->getState() === InvStatesInterface::PROCESSING) {
        $changes['totalProcessing']++;
      }
      elseif ($invalidation->getState() === InvStatesInterface::SUCCEEDED) {
        $changes['totalSucceeded']++;
      }
      elseif ($invalidation->getState() === InvStatesInterface::FAILED) {
        $changes['totalFailed']++;
      }
      elseif ($invalidation->getState() === InvStatesInterface::NOT_SUPPORTED) {
        $changes['totalNotSupported']++;
      }
    }
    foreach ($changes as $stat => $value) {
      if ($value === 0) {
        continue;
      }
      elseif ($value > 0) {
        $this->$stat()->increment($value);
      }
      elseif ($value < 0) {
        $this->$stat()->decrement(abs($value));
      }
    }
  }

  /**
   * Return the current element.
   *
   * @ingroup iterator
   */
  #[\ReturnTypeWillChange]
  public function current() {
    $this->initializeStatistics();
    if ($this->valid()) {
      return $this->instances[$this->position];
    }
    return FALSE;
  }

  /**
   * Return the key of the current element.
   *
   * @ingroup iterator
   */
  #[\ReturnTypeWillChange]
  public function key() {
    $this->initializeStatistics();
    return $this->position;
  }

  /**
   * Move forward to next element.
   *
   * @ingroup iterator
   */
  public function next(): void {
    $this->initializeStatistics();
    ++$this->position;
  }

  /**
   * Rewind the Iterator to the first element.
   *
   * @ingroup iterator
   */
  public function rewind(): void {
    $this->position = 0;
  }

  /**
   * Checks if current position is valid.
   *
   * @ingroup iterator
   */
  public function valid(): bool {
    $this->initializeStatistics();
    return isset($this->instances[$this->position]);
  }

}
