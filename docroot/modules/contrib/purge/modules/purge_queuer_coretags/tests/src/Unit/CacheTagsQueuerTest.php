<?php

namespace Drupal\Tests\purge_queuer_coretags\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\purge\Logger\LoggerServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\InvalidExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Drupal\purge_queuer_coretags\CacheTagsQueuer;
use Drupal\Tests\UnitTestCase;
use Drupal\workspaces\WorkspaceManagerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the CacheTagsQueuer class.
 *
 * @coversDefaultClass \Drupal\purge_queuer_coretags\CacheTagsQueuer
 * @group purge
 */
#[CoversClass(CacheTagsQueuer::class)]
#[Group('purge')]
class CacheTagsQueuerTest extends UnitTestCase {

  /**
   * The tested cache tags queuer.
   *
   * @var \Drupal\purge_queuer_coretags\CacheTagsQueuer
   */
  protected $cacheTagsQueuer;

  /**
   * The mocked config factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The mocked queue service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface
   */
  protected $purgeQueue;

  /**
   * The mocked queuers service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface
   */
  protected $purgeQueuers;

  /**
   * The mocked invalidations factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->purgeQueue = $this->createMock(QueueServiceInterface::class);
    $this->purgeQueuers = $this->createMock(QueuersServiceInterface::class);
    $this->purgeInvalidationFactory = $this->createMock(InvalidationsServiceInterface::class);

    // Create a container with all dependent services in it.
    $this->container = new ContainerBuilder();
    $this->container->set('purge.queue', $this->purgeQueue);
    $this->container->set('purge.queuers', $this->purgeQueuers);
    $this->container->set('purge.invalidation.factory', $this->purgeInvalidationFactory);

    // Initialize the CacheTagsQueuer object and set the container.
    $this->cacheTagsQueuer = new CacheTagsQueuer($this->container);
    $this->assertInstanceOf(CacheTagsInvalidatorInterface::class, $this->cacheTagsQueuer);
  }

  /**
   * Tests that initialization does not load services when queuer is disabled.
   */
  public function testInitializeDoesntLoadWhenQueuerDisabled(): void {
    $this->purgeInvalidationFactory->expects($this->never())->method('get');
    $this->purgeQueue->expects($this->never())->method('add');
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('coretags')
      ->willReturn(FALSE);
    $this->assertNull($this->cacheTagsQueuer->invalidateTags(["1", "2"]));
  }

  /**
   * Tests that nothing gets queued while a non-live workspace is active.
   */
  public function testInvalidateTagsSkipsActiveWorkspace(): void {
    $this->container->set('config.factory', $this->getConfigFactoryStub(
      ['purge_queuer_coretags.settings' => ['blacklist' => []]]
    ));
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('coretags')
      ->willReturn($this->createStub(QueuerBase::class));
    $workspace_manager = $this->createMock(WorkspaceManagerInterface::class);
    $workspace_manager
      ->expects($this->exactly(3))
      ->method('hasActiveWorkspace')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE, TRUE);
    $this->container->set('workspaces.manager', $workspace_manager);
    $this->purgeInvalidationFactory
      ->expects($this->exactly(2))
      ->method('get')
      ->with('tag')
      ->willReturn($this->createStub(InvalidationInterface::class));
    $this->purgeQueue
      ->expects($this->once())
      ->method('add')
      ->with(
        $this->isInstanceOf(QueuerBase::class),
        $this->countOf(2)
      );

    // Tags invalidated in a workspace are neither queued nor remembered, so
    // the same tags do get queued once invalidated outside of the workspace.
    $this->cacheTagsQueuer->invalidateTags(['node:1', 'node_list']);
    $this->cacheTagsQueuer->invalidateTags(['node:1', 'node_list']);
    $this->cacheTagsQueuer->invalidateTags(['node:2']);
  }

  /**
   * Tests cache tag invalidation with various configurations.
   *
   * @dataProvider providerTestInvalidateTags
   */
  #[DataProvider('providerTestInvalidateTags')]
  public function testInvalidateTags($config, array $sets): void {
    $this->container->set('config.factory', $this->getConfigFactoryStub($config));
    // Assert that the queuer plugin is loaded exactly once.
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('coretags')
      ->willReturn($this->createStub(QueuerBase::class)
    );
    // Assert how InvalidationsServiceInterface::get() is called.
    $invs_added_total = array_sum(array_map(
      function ($set) {
        return $set[1];
      },
      $sets
    ));
    $this->purgeInvalidationFactory
      ->expects($this->exactly($invs_added_total))
      ->method('get')
      ->with('tag')
      ->willReturn($this->createStub(InvalidationInterface::class)
    );
    // Assert the precise calls to QueueServiceInterface::add().
    $number_queue_add_calls = count(array_filter($sets, function ($set) {
      return $set[1] !== 0;
    }));
    reset($sets);
    $this->purgeQueue
      ->expects($this->exactly($number_queue_add_calls))
      ->method('add')
      ->with(
        $this->callback(
          function ($queuer) {
            return $queuer instanceof QueuerBase;
          }
        ),
        $this->callback(
          function (array $invs) use (&$sets) {
            $invs_added = current($sets)[1];
            // Sets with 0 shouldn't call ::add() at all, so skip over them.
            if ($invs_added === 0) {
              next($sets);
              $invs_added = current($sets)[1];
            }
            next($sets);
            return is_array($invs) && (count($invs) === $invs_added);
          }
        )
      );
    // Trigger the entire chain by feeding the sets of tags.
    foreach ($sets as $set) {
      $this->cacheTagsQueuer->invalidateTags($set[0]);
    }
  }

  /**
   * Tests that tags which fail validation are skipped and logged, not thrown.
   */
  public function testInvalidateTagsSkipsInvalidTags(): void {
    $this->container->set('config.factory', $this->getConfigFactoryStub([
      'purge_queuer_coretags.settings' => ['blacklist' => []],
    ]));
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('coretags')
      ->willReturn($this->createStub(QueuerBase::class));
    $this->purgeInvalidationFactory
      ->expects($this->exactly(3))
      ->method('get')
      ->willReturnCallback(function ($plugin_id, $expression) {
        if ($expression === 'tag with space') {
          throw new InvalidExpressionException('Tag invalidations cannot contain whitespace.');
        }
        return $this->createStub(InvalidationInterface::class);
      });
    // The skipped tag is logged on the 'queuers' channel of purge's logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger
      ->expects($this->once())
      ->method('error')
      ->with($this->anything(), $this->callback(function (array $context) {
        return $context['@tag'] === 'tag with space';
      }));
    $purge_logger = $this->createMock(LoggerServiceInterface::class);
    $purge_logger
      ->expects($this->once())
      ->method('get')
      ->with('queuers')
      ->willReturn($logger);
    $this->container->set('purge.logger', $purge_logger);
    // Only the two valid tags reach the queue.
    $this->purgeQueue
      ->expects($this->once())
      ->method('add')
      ->with($this->anything(), $this->countOf(2));
    $this->cacheTagsQueuer->invalidateTags(['node:1', 'tag with space', 'node:2']);
    // The invalid tag is remembered, so it isn't retried within the request.
    $this->cacheTagsQueuer->invalidateTags(['tag with space']);
  }

  /**
   * Provides test data for testInvalidateTags().
   */
  public static function providerTestInvalidateTags(): array {
    $blacklist = [
      'purge_queuer_coretags.settings' => [
        'blacklist' => [
          'menu',
          'node',
        ],
      ],
    ];
    return [
      // Two calls to ::invalidateTags(), 'node:5' should get blacklisted.
      [
        $blacklist,
        [
          [['block:1'], 1],
          [['node:5'], 0],
          [['extension:views', 'baz'], 2],
        ],
      ],
      // One call to ::invalidateTags(), with 1 and 3 tags respectively.
      [
        $blacklist,
        [
          [['menu:main'], 0],
          [['NODE:5', 'foo', 'bar', 'bar'], 3],
        ],
      ],
      // Blacklist entries are substring matches, not prefixes (#3436188):
      // ':revisions' and '_list' match mid-tag, 'node:' matches at the start.
      [
        [
          'purge_queuer_coretags.settings' => [
            'blacklist' => ['node:', '_list', ':revisions'],
          ],
        ],
        [
          [
            [
              'node:5',
              'node:5:revisions',
              'taxonomy_term:3:revisions',
              'node_list',
              'node_list:article',
              'user_list',
            ],
            0,
          ],
          [['config:node.type.article', 'user:1', 'block_view'], 3],
        ],
      ],
      // One call to ::invalidateTags() with 4 tags.
      [
        ['purge_queuer_coretags.settings' => ['blacklist' => []]],
        [
          [['node:5', 'foo:2', 'foo:3', 'bar:baz'], 4],
        ],
      ],
      // Five calls to ::invalidateTags() with varying number of tags.
      [
        ['purge_queuer_coretags.settings' => ['blacklist' => []]],
        [
          [['a', 'b'], 2],
          [['c', 'd'], 2],
          [['e', 'f'], 2],
          [['g', 'h', 'i'], 3],
          [['j'], 1],
        ],
      ],
    ];
  }

}
