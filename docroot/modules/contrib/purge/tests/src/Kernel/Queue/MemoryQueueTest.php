<?php

namespace Drupal\Tests\purge\Kernel\Queue;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge\Plugin\Purge\Queue\MemoryQueue.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class MemoryQueueTest extends PluginTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'memory';

}
