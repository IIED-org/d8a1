<?php

declare(strict_types=1);

namespace Drupal\purge_queuer_pathalias\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\purge_queuer_pathalias\PathAliasQueuer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Path alias entity hooks that hand changed aliases to the queuer.
 */
final class PathAliasHooks {

  public function __construct(
    #[Autowire(service: 'purge_queuer_pathalias.queuer')]
    protected readonly PathAliasQueuer $queuer,
    #[Autowire(service: 'logger.factory')]
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for path_alias entities.
   */
  #[Hook('path_alias_insert')]
  public function pathAliasInsert(EntityInterface $entity): void {
    $this->queue($entity, 'insert');
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for path_alias entities.
   */
  #[Hook('path_alias_update')]
  public function pathAliasUpdate(EntityInterface $entity): void {
    $this->queue($entity, 'update');
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for path_alias entities.
   */
  #[Hook('path_alias_delete')]
  public function pathAliasDelete(EntityInterface $entity): void {
    $this->queue($entity, 'delete');
  }

  /**
   * Hand the changed path alias over to the queuer.
   *
   * All expected failure modes are handled inside the queuer itself. The catch
   * here is the last line of defense: a misconfigured queue backend or purger
   * should never be able to make saving a path alias fatal.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The path alias entity that was inserted, updated or deleted.
   * @param string $operation
   *   Either 'insert', 'update' or 'delete'.
   */
  private function queue(EntityInterface $entity, string $operation): void {
    try {
      $this->queuer->queue($entity, $operation);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('purge')->error("purge_queuer_pathalias failed on @operation: @message", [
        '@operation' => $operation,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
