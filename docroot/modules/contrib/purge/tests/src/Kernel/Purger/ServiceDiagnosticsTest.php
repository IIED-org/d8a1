<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\Purger;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Purger\Exception\DiagnosticsException;
use Drupal\Tests\purge\Kernel\KernelServiceTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests diagnostic error handling in the purge.purgers service.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class ServiceDiagnosticsTest extends KernelServiceTestBase {

  /**
   * The name of the service as defined in services.yml.
   *
   * @var string
   */
  protected $serviceId = 'purge.purgers';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'purge_purger_test',
    'purge_processor_test',
    'purge_check_purger_error_test',
  ];

  /**
   * Set up the test.
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    $this->installConfig(['purge_processor_test']);
    $this->initializeProcessorsService(['a']);
    $this->initializeInvalidationFactoryService();
  }

  /**
   * Invalidate the given objects through the enabled purgers.
   *
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface[] $invalidations
   *   The invalidation objects to offer to the purgers service.
   */
  protected function invalidate(array $invalidations): void {
    $this->initializeService();
    $this->service->invalidate($this->purgeProcessors->get('a'), $invalidations);
  }

  /**
   * Tests that a purger-bound error only blocks that purger.
   */
  public function testPurgerSpecificErrorBlocksOnlyThatPurger(): void {
    $this->initializePurgersService(['good', 'onfire']);
    $tag = $this->purgeInvalidationFactory->get('tag', 'node:1');
    $path = $this->purgeInvalidationFactory->get('path', 'node/1');

    // The 'onfire' purger throws when invoked, so reaching this point without
    // an exception proves it was skipped while the 'good' purger still ran.
    $this->invalidate([$tag, $path]);

    // The tag is supported by both, so it succeeded for 'good' and got marked
    // FAILED for the blocked purger, which makes it eligible for a retry.
    $this->assertEquals(InvalidationInterface::FAILED, $tag->getState());
    $this->assertEquals([
      'id0' => InvalidationInterface::SUCCEEDED,
      'id1' => InvalidationInterface::FAILED,
    ], $tag->getStates());

    // The path is not supported by the blocked purger, so it fully succeeded.
    $this->assertEquals(InvalidationInterface::SUCCEEDED, $path->getState());
    $this->assertEquals([
      'id0' => InvalidationInterface::SUCCEEDED,
      'id1' => InvalidationInterface::NOT_SUPPORTED,
    ], $path->getStates());

    // On a retry, the succeeded purger is skipped and the blocked purger is
    // still blocked, so the outcome remains the same.
    $this->invalidate([$tag]);
    $this->assertEquals(InvalidationInterface::FAILED, $tag->getState());
    $this->assertEquals([
      'id0' => InvalidationInterface::SUCCEEDED,
      'id1' => InvalidationInterface::FAILED,
    ], $tag->getStates());
  }

  /**
   * Tests that a purger-bound error blocks everything when no purger remains.
   */
  public function testPurgerSpecificErrorBlocksSolePurger(): void {
    $this->initializePurgersService(['onfire']);
    $tag = $this->purgeInvalidationFactory->get('tag', 'node:1');
    $this->expectException(DiagnosticsException::class);
    $this->expectExceptionMessage('This is a purger error for testing.');
    $this->invalidate([$tag]);
  }

  /**
   * Tests that an error not bound to any purger still blocks all purgers.
   */
  public function testGlobalErrorBlocksAllPurgers(): void {
    // Enabling a module rebuilds the container, so drop the stale services.
    $this->enableModules(['purge_check_error_test']);
    $this->purgeProcessors = NULL;
    $this->purgePurgers = NULL;
    $this->purgeInvalidationFactory = NULL;
    $this->service = NULL;
    $this->initializeProcessorsService(['a']);
    $this->initializeInvalidationFactoryService();
    $this->initializePurgersService(['good', 'onfire']);
    $tag = $this->purgeInvalidationFactory->get('tag', 'node:1');
    $this->expectException(DiagnosticsException::class);
    $this->invalidate([$tag]);
  }

}
