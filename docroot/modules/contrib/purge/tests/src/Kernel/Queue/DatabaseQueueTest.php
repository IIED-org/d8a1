<?php

namespace Drupal\Tests\purge\Kernel\Queue;

use Drupal\purge\Plugin\Purge\Queue\DatabaseQueue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge\Plugin\Purge\Queue\DatabaseQueue.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class DatabaseQueueTest extends PluginTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'database';

  /**
   * Tests that every code path hands out item IDs of one consistent type.
   *
   * PDO returns integer columns as strings on MySQL and SQLite, whereas
   * \Drupal\purge\Plugin\Purge\Queue\TxBuffer::getByProperty() compares item
   * IDs strictly. The queue therefore has to normalize IDs on every path.
   */
  public function testItemIdTypeConsistency(): void {
    $this->assertItemIdType($this->queue, 'assertIsInt');
  }

  /**
   * Tests that subclasses can use non-integer item IDs via ::castItemId().
   */
  public function testCastItemIdOverride(): void {
    $queue = new class($this->container->get('database')) extends DatabaseQueue {

      /**
       * {@inheritdoc}
       */
      protected function castItemId($item_id) {
        return (string) $item_id;
      }

    };
    $this->assertItemIdType($queue, 'assertIsString');
  }

  /**
   * Asserts the item ID type returned by every queue method that returns IDs.
   *
   * @param \Drupal\purge\Plugin\Purge\Queue\DatabaseQueue $queue
   *   The queue under test.
   * @param string $assertion
   *   The PHPUnit type assertion, e.g. 'assertIsInt'.
   */
  protected function assertItemIdType(DatabaseQueue $queue, string $assertion): void {
    $queue->deleteQueue();

    $id = $queue->createItem('single');
    $this->$assertion($id);
    $ids = $queue->createItemMultiple(['multi 1', 'multi 2', 'multi 3']);
    $this->assertCount(3, $ids);
    foreach ($ids as $multi_id) {
      $this->$assertion($multi_id);
    }
    $this->assertEquals(4, $queue->numberOfItems());

    foreach ($queue->selectPage() as $item) {
      $this->$assertion($item->item_id);
    }

    $single = $queue->claimItem(3600);
    $this->$assertion($single->item_id);
    $this->assertEquals($id, $single->item_id);
    $this->assertSame('single', $single->data);
    $claims = $queue->claimItemMultiple(10, 3600);
    $this->assertCount(3, $claims);
    foreach ($claims as $key => $claim) {
      $this->$assertion($claim->item_id);
      $this->assertEquals($ids[$key], $claim->item_id);
    }

    // The IDs must still be usable to release and delete the items.
    $single->data = 'single changed';
    $this->assertTrue($queue->releaseItem($single));
    $this->assertSame('single changed', $queue->claimItem(3600)->data);
    $claims[0]->data = 'multi 1 changed';
    $this->assertSame([], $queue->releaseItemMultiple($claims));
    $this->assertSame('multi 1 changed', $queue->claimItem(3600)->data);
    $queue->deleteItem($single);
    $queue->deleteItemMultiple($claims);
    $this->assertEquals(0, $queue->numberOfItems());
  }

}
