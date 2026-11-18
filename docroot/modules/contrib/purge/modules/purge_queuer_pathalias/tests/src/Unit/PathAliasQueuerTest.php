<?php

namespace Drupal\Tests\purge_queuer_pathalias\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\purge\Logger\LoggerChannelPartInterface;
use Drupal\purge\Logger\LoggerServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Drupal\purge_queuer_pathalias\PathAliasQueuer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the PathAliasQueuer class.
 *
 * @coversDefaultClass \Drupal\purge_queuer_pathalias\PathAliasQueuer
 * @group purge
 */
#[CoversClass(PathAliasQueuer::class)]
#[Group('purge')]
class PathAliasQueuerTest extends UnitTestCase {

  /**
   * The default settings of the queuer.
   *
   * @var array
   */
  protected $settings = [
    'invalidation_type' => 'auto',
    'base_urls' => [],
    'queue_insert' => TRUE,
    'queue_update' => TRUE,
    'queue_delete' => TRUE,
    'expand_undefined_langcode' => TRUE,
    'skip_syncing' => TRUE,
    'max_per_request' => 100,
  ];

  /**
   * The stubbed language manager.
   *
   * @var \PHPUnit\Framework\MockObject\Stub|\Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The mocked invalidations factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * The stubbed purgers service.
   *
   * @var \PHPUnit\Framework\MockObject\Stub|\Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->purgeQueue = $this->createMock(QueueServiceInterface::class);
    $this->purgeQueuers = $this->createMock(QueuersServiceInterface::class);
    $this->purgeInvalidationFactory = $this->createMock(InvalidationsServiceInterface::class);
    $this->purgePurgers = $this->createStub(PurgersServiceInterface::class);
    $this->languageManager = $this->createStub(LanguageManagerInterface::class);
    $language = $this->createStub(LanguageInterface::class);
    $language->method('getId')->willReturn('en');
    $this->languageManager->method('getLanguage')->willReturn($language);
    $this->languageManager->method('getDefaultLanguage')->willReturn($language);
    $this->languageManager->method('getLanguages')->willReturn(['en' => $language]);

    // URL generation for unrouted 'base:' URIs runs through the assembler.
    $assembler = $this->createStub(UnroutedUrlAssemblerInterface::class);
    $assembler->method('assemble')->willReturnCallback(
      function ($uri, array $options = [], $collect_bubbleable_metadata = FALSE) {
        $path = substr($uri, strlen('base:'));
        $url = empty($options['absolute']) ? $path : 'http://example.com' . $path;
        return $collect_bubbleable_metadata
          ? (new GeneratedUrl())->setGeneratedUrl($url)
          : $url;
      }
    );
    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', $assembler);
    \Drupal::setContainer($container);
  }

  /**
   * Instantiate the queuer with the given settings.
   *
   * @param array $settings
   *   Settings overriding the module defaults.
   *
   * @return \Drupal\purge_queuer_pathalias\PathAliasQueuer
   *   The queuer under test.
   */
  protected function queuer(array $settings = []) {
    $logger = $this->createStub(LoggerServiceInterface::class);
    $logger
      ->method('get')
      ->willReturn($this->createStub(LoggerChannelPartInterface::class));
    $config_factory = $this->getConfigFactoryStub([
      PathAliasQueuer::CONFIG => $settings + $this->settings,
      'language.negotiation' => ['url.source' => 'path_prefix'],
    ]);
    return new PathAliasQueuer(
      $this->purgeQueuers,
      $this->purgeInvalidationFactory,
      $this->purgeQueue,
      $this->purgePurgers,
      $logger,
      $config_factory,
      $this->languageManager,
      new RequestStack()
    );
  }

  /**
   * Create a stubbed path alias entity.
   *
   * @param string $alias
   *   The alias to return from the 'alias' field.
   * @param bool $syncing
   *   Whether the entity reports itself as syncing.
   *
   * @return \PHPUnit\Framework\MockObject\Stub|\Drupal\Core\Entity\ContentEntityInterface
   *   The stubbed entity.
   */
  protected function entity($alias = '/foo', $syncing = FALSE) {
    // ContentEntityInterface already extends both FieldableEntityInterface and
    // SynchronizableInterface, which is all the queuer relies upon.
    $entity = $this->createStub(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('path_alias');
    $entity->method('isSyncing')->willReturn($syncing);
    $entity->method('hasField')->willReturn(TRUE);
    $entity->method('get')->willReturnCallback(
      function ($field) use ($alias) {
        $item = new class() {
          /**
           * The value of the field item.
           *
           * @var string
           */
          public $value;

        };
        $item->value = ($field === 'alias') ? $alias : 'en';
        return $item;
      }
    );
    return $entity;
  }

  /**
   * Assert that no invalidation is instantiated nor queued.
   */
  protected function assertNothingQueued(): void {
    $this->purgeInvalidationFactory->expects($this->never())->method('get');
    $this->purgeQueue->expects($this->never())->method('add');
  }

  /**
   * Tests that a disabled queuer plugin short-circuits everything.
   */
  public function testQueuerDisabled(): void {
    $this->assertNothingQueued();
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('pathalias')
      ->willReturn(FALSE);
    $this->queuer()->queue($this->entity(), 'insert');
  }

  /**
   * Tests that entities of other types are ignored.
   */
  public function testOtherEntityTypesIgnored(): void {
    $this->assertNothingQueued();
    $this->purgeQueuers->expects($this->never())->method('get');
    $entity = $this->createStub(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $this->queuer()->queue($entity, 'insert');
  }

  /**
   * Tests that syncing entities are skipped.
   */
  public function testSyncingSkipped(): void {
    $this->assertNothingQueued();
    $this->purgeQueuers->expects($this->never())->method('get');
    $this->queuer()->queue($this->entity('/foo', TRUE), 'insert');
  }

  /**
   * Tests that the per-operation toggles are honored.
   *
   * @dataProvider providerTestOperationToggles
   */
  #[DataProvider('providerTestOperationToggles')]
  public function testOperationToggles(string $operation): void {
    $this->assertNothingQueued();
    $this->purgeQueuers->expects($this->never())->method('get');
    $queuer = $this->queuer(['queue_' . $operation => FALSE]);
    $queuer->queue($this->entity(), $operation);
  }

  /**
   * Provides test data for testOperationToggles().
   */
  public static function providerTestOperationToggles(): array {
    return [['insert'], ['update'], ['delete']];
  }

  /**
   * Tests type resolution against the types supported by the purgers.
   *
   * @dataProvider providerTestResolveType
   */
  #[DataProvider('providerTestResolveType')]
  public function testResolveType(string $configured, array $supported, $expected): void {
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('pathalias')
      ->willReturn($this->createStub(QueuerBase::class));
    $this->purgePurgers->method('getTypes')->willReturn($supported);

    if (is_null($expected)) {
      $this->assertNothingQueued();
    }
    else {
      $this->purgeInvalidationFactory
        ->expects($this->once())
        ->method('get')
        ->with($expected);
      $this->purgeQueue->expects($this->once())->method('add');
    }

    $this->queuer(['invalidation_type' => $configured])
      ->queue($this->entity(), 'insert');
  }

  /**
   * Provides test data for testResolveType().
   */
  public static function providerTestResolveType(): array {
    return [
      ['auto', ['tag'], NULL],
      ['auto', ['tag', 'url', 'path'], 'url'],
      ['auto', ['tag', 'path'], 'path'],
      ['url', ['path'], NULL],
      ['path', ['url', 'path'], 'path'],
    ];
  }

  /**
   * Tests that a purger disappearing mid-flight doesn't queue anything.
   */
  public function testTypeUnsupportedException(): void {
    $this->purgeQueuers
      ->expects($this->once())
      ->method('get')
      ->with('pathalias')
      ->willReturn($this->createStub(QueuerBase::class));
    $this->purgePurgers->method('getTypes')->willReturn(['url']);
    $this->purgeInvalidationFactory
      ->expects($this->once())
      ->method('get')
      ->willThrowException(new TypeUnsupportedException('url'));
    $this->purgeQueue->expects($this->never())->method('add');
    $this->queuer()->queue($this->entity(), 'insert');
  }

}
