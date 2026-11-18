<?php

namespace Drupal\purge_queuer_coretags;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\InvalidExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queues invalidated cache tags.
 */
class CacheTagsQueuer implements CacheTagsInvalidatorInterface {

  /**
   * The container service.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Construct the Cache Tags Queuer.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container service.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * A list of strings; tags containing any of them do not go into the queue.
   *
   * Matching is done with strpos(), so an entry matches anywhere inside the
   * tag and not only at its start.
   *
   * @var string[]
   */
  protected $blacklistedTagPrefixes;

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the invalidation of the same cache tag multiple times.
   *
   * @var string[]
   */
  protected $invalidatedTags = [];

  /**
   * The 'purge.invalidation.factory' service.
   *
   * @var null|\Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * The 'purge.queue' service.
   *
   * @var null|\Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface
   */
  protected $purgeQueue;

  /**
   * The queuer plugin or FALSE when disabled, NULL when not yet initialized.
   *
   * @var null|false|\Drupal\purge_queuer_coretags\Plugin\Purge\Queuer\CoreTagsQueuer
   */
  protected $queuer;

  /**
   * Determines whether a non-live workspace is currently active.
   *
   * Content saved in a non-live workspace is only visible to users previewing
   * that workspace, so the cache tags that core invalidates for it cannot
   * affect anything an external cache serves to the public. Those tags are
   * queued once the workspace gets published, as core publishes outside of any
   * active workspace.
   *
   * @return bool
   *   TRUE when the workspaces module is installed and a workspace is active.
   */
  protected function hasActiveWorkspace() {
    return $this->container->has('workspaces.manager')
      && $this->container->get('workspaces.manager')->hasActiveWorkspace();
  }

  /**
   * Initialize the invalidation factory and queue service.
   *
   * @return bool
   *   TRUE when everything is available, FALSE when our plugin is disabled.
   */
  protected function initialize() {
    if (is_null($this->queuer)) {
      // If the coretags queuer plugin doesn't load, this object is not allowed
      // to operate and thus loads the least possible dependencies.
      $this->queuer = $this->container->get('purge.queuers')->get('coretags');
      if ($this->queuer !== FALSE) {
        $this->purgeInvalidationFactory = $this->container->get('purge.invalidation.factory');
        $this->purgeQueue = $this->container->get('purge.queue');
        $this->blacklistedTagPrefixes = $this->container
          ->get('config.factory')
          ->get('purge_queuer_coretags.settings')
          ->get('blacklist');

        // Ensure config is an array.
        if (!is_array($this->blacklistedTagPrefixes)) {
          $this->blacklistedTagPrefixes = [];
        }
      }
    }
    return $this->queuer !== FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Queues invalidated cache tags as tag purgeables.
   */
  public function invalidateTags(array $tags) {
    if (!$this->initialize()) {
      return;
    }

    // Skip tags invalidated while a non-live workspace is active, they'll be
    // invalidated again when the workspace gets published. The tags are not
    // remembered as invalidated either, so that publishing them later on in
    // the same request still queues them.
    if ($this->hasActiveWorkspace()) {
      return;
    }

    // Iterate each given tag and only add those we didn't queue before.
    $invalidations = [];
    foreach ($tags as $tag) {
      if (!in_array($tag, $this->invalidatedTags)) {

        // Check the tag against the blacklist and skip if it matches. This is
        // deliberately a substring match, not a prefix match: sites rely on
        // entries such as ':revisions' or '_list' matching mid-tag.
        $blacklisted = FALSE;
        foreach ($this->blacklistedTagPrefixes as $prefix) {
          if (strpos($tag, $prefix) !== FALSE) {
            $blacklisted = TRUE;
          }
        }
        if (!$blacklisted) {
          try {
            $invalidations[] = $this->purgeInvalidationFactory->get('tag', $tag);
            $this->invalidatedTags[] = $tag;
          }
          catch (TypeUnsupportedException $e) {
            // When there's no purger enabled, don't bother queueing tags.
            return;
          }
          catch (InvalidExpressionException $e) {
            // Tags that purgers can never invalidate (e.g. containing spaces)
            // are skipped: core is calling us from within entity and config
            // saves, where throwing would take the whole request down.
            $this->invalidatedTags[] = $tag;
            $this->container->get('purge.logger')->get('queuers')->error(
              "Skipped cache tag '@tag': @reason",
              ['@tag' => $tag, '@reason' => $e->getMessage()]
            );
          }
        }
      }
    }

    // The queue buffers invalidations, though we don't care about that here.
    if (count($invalidations)) {
      $this->purgeQueue->add($this->queuer, $invalidations);
    }
  }

}
