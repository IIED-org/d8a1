<?php

namespace Drupal\Tests\purge_queuer_pathalias\Kernel;

use Drupal\purge_queuer_pathalias\PathAliasQueuer;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge_queuer_pathalias\PathAliasQueuer.
 *
 * @coversDefaultClass \Drupal\purge_queuer_pathalias\PathAliasQueuer
 * @group purge
 */
#[CoversClass(PathAliasQueuer::class)]
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class PathAliasQueuerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'path_alias',
    'purge_purger_test',
    'purge_queuer_pathalias',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    $this->installEntitySchema('path_alias');
    $this->installConfig(['purge_queuer_pathalias']);
    $this->initializeQueuersService(['pathalias']);
    $this->initializePurgersService(['good']);
    $this->initializeQueueService();
    // Keep generated URLs predictable regardless of the test request context.
    $this->setQueuerSettings(['base_urls' => ['http://example.com']]);
  }

  /**
   * Overwrite one or more of the queuer's settings.
   *
   * @param array $settings
   *   Associative array of setting keys and their values.
   */
  protected function setQueuerSettings(array $settings): void {
    $config = $this->config('purge_queuer_pathalias.settings');
    foreach ($settings as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
  }

  /**
   * Get the path alias entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The path alias entity storage.
   */
  protected function storage() {
    return $this->container->get('entity_type.manager')
      ->getStorage('path_alias');
  }

  /**
   * Create and save a path alias entity.
   *
   * @param string $alias
   *   The alias, with leading slash.
   * @param string $path
   *   The system path the alias points to, with leading slash.
   * @param string $langcode
   *   The langcode of the alias.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The saved path alias entity.
   */
  protected function createAlias($alias, $path = '/node/1', $langcode = 'en') {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage()->create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode,
    ]);
    $entity->save();
    return $entity;
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
    $queued = [];
    foreach ((array) $this->purgeQueue->claim(100) as $invalidation) {
      $queued[] = $invalidation->getType() . '|' . $invalidation->getExpression();
    }
    sort($queued);
    return $queued;
  }

  /**
   * Tests that creating an alias queues its URL.
   */
  public function testInsert(): void {
    $this->createAlias('/foo');
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
  }

  /**
   * Tests that changing an alias queues both the new and the old URL.
   */
  public function testUpdateQueuesOldAndNewAlias(): void {
    $entity = $this->createAlias('/foo');
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
    $entity->set('alias', '/bar');
    $entity->save();
    // '/foo' gets queued again even though the creation queued it already:
    // it responds with a 404 now, which is a different state to purge.
    $this->assertSame([
      'url|http://example.com/bar',
      'url|http://example.com/foo',
    ], $this->claimQueued());
  }

  /**
   * Tests that saving without changing the alias queues it only once.
   */
  public function testUpdateWithoutAliasChange(): void {
    $entity = $this->createAlias('/foo');
    $entity->set('path', '/node/2');
    $entity->save();
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
  }

  /**
   * Tests that deleting an alias queues its URL.
   */
  public function testDelete(): void {
    $entity = $this->createAlias('/foo');
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
    $entity->delete();
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
  }

  /**
   * Tests that a create and a delete in one request both get queued.
   *
   * Deduplicating the delete against the earlier create would leave the CDN
   * serving whatever it cached in between - a 200 for a URL that is a 404
   * again by the end of the request.
   */
  public function testInsertAndDeleteInSameRequest(): void {
    $this->createAlias('/foo')->delete();
    $this->assertSame([
      'url|http://example.com/foo',
      'url|http://example.com/foo',
    ], $this->claimQueued());
  }

  /**
   * Tests that deletion can be turned off.
   */
  public function testDeleteDisabled(): void {
    $this->setQueuerSettings(['queue_insert' => FALSE, 'queue_delete' => FALSE]);
    $this->createAlias('/foo')->delete();
    $this->assertSame([], $this->claimQueued());
  }

  /**
   * Tests that insertion can be turned off.
   */
  public function testInsertDisabled(): void {
    $this->setQueuerSettings(['queue_insert' => FALSE]);
    $this->createAlias('/foo');
    $this->assertSame([], $this->claimQueued());
  }

  /**
   * Tests that path invalidations are queued without a leading slash.
   */
  public function testPathInvalidationType(): void {
    $this->setQueuerSettings(['invalidation_type' => 'path']);
    $this->createAlias('/foo/bar');
    $this->assertSame(['path|foo/bar'], $this->claimQueued());
  }

  /**
   * Tests that 'auto' falls back to path when no purger supports url.
   */
  public function testAutoFallsBackToPath(): void {
    // The 'withform' test purger only supports 'path' invalidations.
    $this->initializePurgersService(['withform']);
    $this->createAlias('/foo/bar');
    $this->assertSame(['path|foo/bar'], $this->claimQueued());
  }

  /**
   * Tests that a configured type unsupported by the purgers queues nothing.
   */
  public function testUnsupportedConfiguredType(): void {
    // The 'a' test purger only supports 'everything' invalidations.
    $this->initializePurgersService(['a']);
    $this->createAlias('/foo');
    $this->assertSame([], $this->claimQueued());
  }

  /**
   * Tests that nothing is queued when the queuer plugin is disabled.
   */
  public function testQueuerDisabled(): void {
    $this->initializeQueuersService([], TRUE);
    $this->createAlias('/foo');
    $this->assertSame([], $this->claimQueued());
  }

  /**
   * Tests that the same alias isn't queued twice within one request.
   */
  public function testDeduplication(): void {
    $this->createAlias('/foo', '/node/1');
    $this->createAlias('/foo', '/node/2');
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
  }

  /**
   * Tests that spaces in aliases get encoded instead of throwing.
   */
  public function testAliasWithSpace(): void {
    $this->createAlias('/foo bar');
    $this->assertSame(['url|http://example.com/foo%20bar'], $this->claimQueued());
  }

  /**
   * Tests that the base URL setting overrides the generated host.
   */
  public function testBaseUrlSetting(): void {
    $this->setQueuerSettings(['base_urls' => ['https://cdn.example.com']]);
    $this->createAlias('/foo');
    $this->assertSame(['url|https://cdn.example.com/foo'], $this->claimQueued());
  }

  /**
   * Tests that every configured base URL gets its own invalidation.
   */
  public function testMultipleBaseUrls(): void {
    $this->setQueuerSettings([
      'base_urls' => ['https://one.example.com', 'https://two.example.com'],
    ]);
    $this->createAlias('/foo');
    $this->assertSame([
      'url|https://one.example.com/foo',
      'url|https://two.example.com/foo',
    ], $this->claimQueued());
  }

  /**
   * Tests that path invalidations ignore the configured base URLs.
   */
  public function testMultipleBaseUrlsIgnoredForPaths(): void {
    $this->setQueuerSettings([
      'invalidation_type' => 'path',
      'base_urls' => ['https://one.example.com', 'https://two.example.com'],
    ]);
    $this->createAlias('/foo');
    $this->assertSame(['path|foo'], $this->claimQueued());
  }

  /**
   * Tests that the per-request maximum caps what gets queued.
   */
  public function testMaxPerRequest(): void {
    $this->setQueuerSettings(['max_per_request' => 2]);
    $this->createAlias('/one');
    $this->createAlias('/two');
    $this->createAlias('/three');
    $this->assertSame([
      'url|http://example.com/one',
      'url|http://example.com/two',
    ], $this->claimQueued());
  }

  /**
   * Tests that syncing entities are skipped.
   */
  public function testSkipSyncing(): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage()->create([
      'path' => '/node/1',
      'alias' => '/foo',
      'langcode' => 'en',
    ]);
    $entity->setSyncing(TRUE);
    $entity->save();
    $this->assertSame([], $this->claimQueued());
  }

  /**
   * Tests that syncing entities are queued when the guard is turned off.
   */
  public function testSkipSyncingDisabled(): void {
    $this->setQueuerSettings(['skip_syncing' => FALSE]);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage()->create([
      'path' => '/node/1',
      'alias' => '/foo',
      'langcode' => 'en',
    ]);
    $entity->setSyncing(TRUE);
    $entity->save();
    $this->assertSame(['url|http://example.com/foo'], $this->claimQueued());
  }

}
