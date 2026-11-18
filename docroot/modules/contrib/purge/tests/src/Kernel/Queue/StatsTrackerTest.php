<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\Queue;

use Drupal\Core\State\StateInterface;
use Drupal\purge\Plugin\Purge\Queue\StatsTracker;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge\Plugin\Purge\Queue\StatsTracker.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class StatsTrackerTest extends KernelTestBase {

  /**
   * Tests that the statistics are stored in key-value storage.
   */
  public function testStorage(): void {
    $stats = $this->container->get('purge.queue.stats');
    $stats->numberOfItems()->set(5);
    $stats->totalSucceeded()->increment(3);
    $stats->destruct();

    $values = $this->container->get('keyvalue')->get(StatsTracker::COLLECTION)->getAll();
    $this->assertEquals([
      'purge_queue_number_of_items' => 5,
      'purge_queue_total_succeeded' => 3,
    ], $values);
    $this->assertNull($this->container->get('state')->get('purge_queue_number_of_items'));

    // A new tracker reads the stored values back.
    $tracker = new StatsTracker($this->container->get('keyvalue'));
    $this->assertSame(5, $tracker->numberOfItems()->getInteger());
    $this->assertSame(3, $tracker->totalSucceeded()->getInteger());
    $this->assertSame(0, $tracker->totalFailed()->getInteger());
  }

  /**
   * Tests that passing the state service still works, but is deprecated.
   */
  public function testStateArgumentIsDeprecated(): void {
    $this->container->get('keyvalue')->get(StatsTracker::COLLECTION)->set('purge_queue_total_failed', 4);

    // Capture the deprecation directly, which works the same on PHPUnit 9 and
    // PHPUnit 11.
    $deprecations = [];
    set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
      $deprecations[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      $tracker = new StatsTracker($this->container->get('state'));
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame(['Passing the state service to Drupal\purge\Plugin\Purge\Queue\StatsTracker::__construct() is deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Pass the keyvalue service instead. See https://www.drupal.org/project/purge/issues/3635585'], $deprecations);
    $this->assertSame(4, $tracker->totalFailed()->getInteger());
  }

  /**
   * Tests that the state property still works, but is deprecated.
   */
  public function testStatePropertyIsDeprecated(): void {
    $tracker = new class($this->container->get('keyvalue')) extends StatsTracker {

      /**
       * Returns the deprecated state property.
       */
      public function getState(): StateInterface {
        // @phpstan-ignore property.deprecated
        return $this->state;
      }

    };
    $tracker->totalFailed()->set(2);
    $tracker->destruct();

    // Accessing the property is silent, using it triggers the deprecation.
    $deprecations = [];
    set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
      $deprecations[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      $state = $tracker->getState();
      $this->assertSame([], $deprecations);
      $failed = $state->get('purge_queue_total_failed');
      $state->set('purge_queue_total_succeeded', 6);
    }
    finally {
      restore_error_handler();
    }
    $message = 'The Drupal\purge\Plugin\Purge\Queue\StatsTracker::$state property is deprecated in purge:8.x-3.8 and is removed from purge:4.0.0. Use the $storage property instead. See https://www.drupal.org/project/purge/issues/3635585';
    $this->assertSame([$message, $message], $deprecations);

    // The property reads and writes the statistics in key-value storage.
    $this->assertEquals(2, $failed);
    $this->assertSame(6, (new StatsTracker($this->container->get('keyvalue')))->totalSucceeded()->getInteger());
  }

  /**
   * Tests a subclass that does not call the parent constructor.
   *
   * Subclasses written for purge:8.x-3.7 and earlier could set $this->state
   * themselves instead of calling the parent constructor.
   */
  public function testSubclassWithoutParentConstructor(): void {
    $deprecations = [];
    set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
      $deprecations[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      $tracker = new class($this->container->get('state')) extends StatsTracker {

        /**
         * Constructs the tracker the way purge:8.x-3.7 subclasses could.
         *
         * @param \Drupal\Core\State\StateInterface $state
         *   The state service.
         */
        public function __construct(StateInterface $state) {
          // @phpstan-ignore property.deprecated
          $this->state = $state;
        }

      };
      $tracker->totalFailed()->set(3);
      $tracker->destruct();
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame(['Not calling Drupal\purge\Plugin\Purge\Queue\StatsTracker::__construct() from a subclass constructor is deprecated in purge:8.x-3.8 and is required in purge:4.0.0. See https://www.drupal.org/project/purge/issues/3635585'], $deprecations);

    // The statistics are still read from and written to key-value storage.
    $this->assertSame(3, $tracker->totalFailed()->getInteger());
    $this->assertSame(3, (new StatsTracker($this->container->get('keyvalue')))->totalFailed()->getInteger());
  }

  /**
   * Tests that the post-update function moves the statistics out of state.
   */
  public function testPostUpdate(): void {
    $state = $this->container->get('state');
    $state->set('purge_queue_number_of_items', 2.0);
    $state->set('purge_queue_total_failed', 7.0);

    require_once __DIR__ . '/../../../../purge.post_update.php';
    purge_post_update_move_queue_stats_to_key_value();

    $values = $this->container->get('keyvalue')->get(StatsTracker::COLLECTION)->getAll();
    $this->assertEquals([
      'purge_queue_number_of_items' => 2,
      'purge_queue_total_failed' => 7,
    ], $values);
    $state->resetCache();
    $this->assertNull($state->get('purge_queue_number_of_items'));
    $this->assertNull($state->get('purge_queue_total_failed'));
  }

  /**
   * Tests that uninstalling purge deletes the statistics.
   */
  public function testUninstall(): void {
    $stats = $this->container->get('purge.queue.stats');
    $stats->numberOfItems()->set(5);
    $stats->destruct();
    $this->assertNotEmpty($this->container->get('keyvalue')->get(StatsTracker::COLLECTION)->getAll());

    $this->container->get('module_installer')->uninstall(['purge']);
    $this->assertSame([], $this->container->get('keyvalue')->get(StatsTracker::COLLECTION)->getAll());
  }

}
