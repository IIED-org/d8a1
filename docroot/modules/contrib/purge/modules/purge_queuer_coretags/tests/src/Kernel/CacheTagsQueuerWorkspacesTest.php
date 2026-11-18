<?php

declare(strict_types=1);

namespace Drupal\Tests\purge_queuer_coretags\Kernel;

use Drupal\purge_queuer_coretags\CacheTagsQueuer;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workspaces\Entity\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the core tags queuer ignores tags invalidated in a workspace.
 *
 * @coversDefaultClass \Drupal\purge_queuer_coretags\CacheTagsQueuer
 * @group purge
 */
#[CoversClass(CacheTagsQueuer::class)]
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class CacheTagsQueuerWorkspacesTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'entity_test',
    'workspaces',
    'purge_purger_test',
    'purge_queuer_coretags',
  ];

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The non-live workspace used throughout the test.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $stage;

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('entity_test_mulrevpub');
    // The set of workspace association tables differs between core versions.
    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude('workspaces', 'install');
    $schema = $module_handler->invoke('workspaces', 'schema');
    // Drupal 12 returns a schema definition object, earlier versions an array.
    $this->installSchema('workspaces', is_array($schema) ? array_keys($schema) : $schema->tableNames());
    $this->installConfig(['purge_queuer_coretags']);
    $this->initializeQueuersService(['coretags']);
    $this->initializePurgersService(['good']);
    $this->initializeQueueService();

    // Workspaces require an owner that is allowed to view them.
    $this->setUpCurrentUser([], [], TRUE);
    $this->workspaceManager = $this->container->get('workspaces.manager');
    $this->stage = Workspace::create(['id' => 'stage', 'label' => 'Stage']);
    $this->stage->save();
    $this->claimQueued();
  }

  /**
   * Commit the buffer and return all queued invalidations as strings.
   *
   * @return string[]
   *   List of "TYPE|EXPRESSION" strings, sorted alphabetically.
   */
  protected function claimQueued(): array {
    // ::commit() is on QueueService rather than on its interface.
    $this->container->get('purge.queue')->commit();
    $claimed = (array) $this->purgeQueue->claim(100);
    $queued = [];
    foreach ($claimed as $invalidation) {
      $queued[] = $invalidation->getType() . '|' . $invalidation->getExpression();
    }
    $this->purgeQueue->delete($claimed);
    sort($queued);
    return $queued;
  }

  /**
   * Tests that only tags invalidated outside a workspace get queued.
   *
   * @covers ::invalidateTags
   * @covers ::hasActiveWorkspace
   */
  public function testInvalidateTagsInWorkspace(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_mulrevpub');

    // Creating and updating content in a workspace only affects previews of
    // that workspace, so nothing should end up in the queue.
    $this->workspaceManager->setActiveWorkspace($this->stage);
    $this->assertTrue($this->workspaceManager->hasActiveWorkspace());
    $entity = $storage->create(['name' => 'staged']);
    $entity->save();
    $this->assertSame([], $this->claimQueued());
    $entity->set('name', 'staged again')->save();
    $this->assertSame([], $this->claimQueued());

    // Publishing the workspace pushes the content live, which core does
    // outside of the active workspace, so now the tags must get queued.
    $this->stage->publish();
    $queued = $this->claimQueued();
    $this->assertContains('tag|entity_test_mulrevpub:' . $entity->id(), $queued);
    $this->assertContains('tag|entity_test_mulrevpub_list', $queued);

    // Content saved on the live site keeps getting queued as before.
    $this->workspaceManager->switchToLive();
    $this->assertFalse($this->workspaceManager->hasActiveWorkspace());
    $live = $storage->create(['name' => 'live']);
    $live->save();
    $live->set('name', 'live again')->save();
    $this->assertContains('tag|entity_test_mulrevpub:' . $live->id(), $this->claimQueued());
  }

}
