<?php

namespace Drupal\purge_queuer_pathalias;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\purge\Logger\LoggerServiceInterface;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\InvalidExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\MissingExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\TypeUnsupportedException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Queues URL or path invalidations for changed path aliases.
 *
 * Content delivery networks cache 404 responses for URLs that don't exist yet.
 * Since the '4xx-response' cache tag is blacklisted by default in
 * purge_queuer_coretags - un-blacklisting it causes site-wide purge stampedes -
 * those cached 404s remain stuck at the edge once the alias does get created.
 * This class surgically queues the single URL that changed instead.
 *
 * The entity parameters deliberately declare the generic
 * \Drupal\Core\Entity\EntityInterface and their fields are read through the
 * field API, so that this class never references \Drupal\path_alias\* and can
 * be loaded even when the optional path_alias module is absent.
 */
class PathAliasQueuer {

  /**
   * The name of the configuration object.
   *
   * @var string
   */
  const CONFIG = 'purge_queuer_pathalias.settings';

  /**
   * The queuer plugin ID.
   *
   * @var string
   */
  const QUEUER = 'pathalias';

  /**
   * The entity type this queuer acts upon.
   *
   * @var string
   */
  const ENTITY_TYPE = 'path_alias';

  /**
   * The invalidation types supported by this queuer, in order of preference.
   *
   * @var string[]
   */
  const TYPES = ['url', 'path'];

  /**
   * The factory for configuration objects.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger channel part for this queuer.
   *
   * @var \Drupal\purge\Logger\LoggerChannelPartInterface
   */
  protected $logger;

  /**
   * The 'purge.invalidation.factory' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * The 'purge.purgers' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * The 'purge.queue' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface
   */
  protected $purgeQueue;

  /**
   * The 'purge.queuers' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface
   */
  protected $purgeQueuers;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The last queued state per expression, keyed "TYPE|EXPRESSION".
   *
   * The value tells whether the URL resolved or 404'd as of the operation that
   * queued it. Repeating the same state - both a node and its alias getting
   * saved within one request - is redundant and gets skipped, whereas a change
   * of state always gets queued: an alias that is created and then deleted
   * within one request has to be purged again after the delete, or the CDN
   * keeps serving whatever it cached in between.
   *
   * @var bool[]
   */
  protected $queued = [];

  /**
   * The number of invalidations queued during this request.
   *
   * Counted separately from ::$queued, which holds at most one entry per
   * expression no matter how often that expression gets requeued.
   *
   * @var int
   */
  protected $queuedCount = 0;

  /**
   * Whether the per-request limit was logged already.
   *
   * @var bool
   */
  protected $limitLogged = FALSE;

  /**
   * Construct the path alias queuer.
   *
   * @param \Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface $purge_queuers
   *   The 'purge.queuers' service.
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface $purge_invalidation_factory
   *   The 'purge.invalidation.factory' service.
   * @param \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $purge_queue
   *   The 'purge.queue' service.
   * @param \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface $purge_purgers
   *   The 'purge.purgers' service.
   * @param \Drupal\purge\Logger\LoggerServiceInterface $purge_logger
   *   The 'purge.logger' service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(QueuersServiceInterface $purge_queuers, InvalidationsServiceInterface $purge_invalidation_factory, QueueServiceInterface $purge_queue, PurgersServiceInterface $purge_purgers, LoggerServiceInterface $purge_logger, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, RequestStack $request_stack) {
    $this->purgeQueuers = $purge_queuers;
    $this->purgeInvalidationFactory = $purge_invalidation_factory;
    $this->purgeQueue = $purge_queue;
    $this->purgePurgers = $purge_purgers;
    $this->logger = $purge_logger->get('queuer_pathalias');
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Queue invalidations for the alias(es) affected by this entity operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The path alias entity that was inserted, updated or deleted.
   * @param string $operation
   *   Either 'insert', 'update' or 'delete'.
   */
  public function queue(EntityInterface $entity, $operation) {
    if ($entity->getEntityTypeId() !== self::ENTITY_TYPE) {
      return;
    }
    if (!in_array($operation, ['insert', 'update', 'delete'], TRUE)) {
      return;
    }

    $config = $this->configFactory->get(self::CONFIG);
    if (!$config->get('queue_' . $operation)) {
      return;
    }

    // Bulk operations - migrations, content staging, config imports - can
    // create thousands of aliases and would flood the queue.
    if ($config->get('skip_syncing') && $entity instanceof SynchronizableInterface && $entity->isSyncing()) {
      return;
    }

    // Bail out when the site administrator disabled this queuer.
    $queuer = $this->purgeQueuers->get(self::QUEUER);
    if ($queuer === FALSE) {
      return;
    }

    // Bail out when no enabled purger supports url or path invalidation.
    if (is_null($type = $this->resolveType($config))) {
      return;
    }

    // Gather the alias(es) that need invalidating, mapped onto whether that
    // alias resolves now that the operation completed. On updates the previous
    // alias needs invalidating too, as that URL just started responding with a
    // 404.
    $aliases = [];
    if (!is_null($alias = $this->readAlias($entity))) {
      $aliases[$alias] = ($operation !== 'delete');
    }
    if ($operation === 'update' && !is_null($original = $this->getOriginal($entity))) {
      $old = $this->readAlias($original);
      if (!is_null($old) && !isset($aliases[$old])) {
        $aliases[$old] = FALSE;
      }
    }
    if (!count($aliases)) {
      return;
    }

    $invalidations = $this->buildInvalidations($type, $aliases, $this->resolveLangcodes($entity, $config), $config);
    if (count($invalidations)) {
      $this->purgeQueue->add($queuer, $invalidations);
    }
  }

  /**
   * Instantiate invalidation objects for all alias/language combinations.
   *
   * @param string $type
   *   The invalidation type, either 'url' or 'path'.
   * @param bool[] $aliases
   *   The aliases to invalidate with leading slashes, mapped onto whether that
   *   alias resolves now that the operation completed.
   * @param string[] $langcodes
   *   The langcodes to generate URLs for.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   *
   * @return \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface[]
   *   The instantiated invalidation objects.
   */
  protected function buildInvalidations($type, array $aliases, array $langcodes, ImmutableConfig $config) {
    $invalidations = [];
    $limit = (int) $config->get('max_per_request');
    foreach ($aliases as $alias => $resolves) {
      foreach ($langcodes as $langcode) {
        $expressions = $this->buildExpressions($type, $alias, $langcode, $config);
        foreach ($expressions as $expression) {
          $key = $type . '|' . $expression;
          // Only skip when this expression was last queued in the very same
          // state - see ::$queued.
          if (array_key_exists($key, $this->queued) && ($this->queued[$key] === $resolves)) {
            continue;
          }
          if ($limit && ($this->queuedCount >= $limit)) {
            if (!$this->limitLogged) {
              $this->limitLogged = TRUE;
              $this->logger->warning("hit the configured limit of @limit invalidations for this request, skipping the rest.", ['@limit' => $limit]);
            }
            return $invalidations;
          }
          try {
            $invalidations[] = $this->purgeInvalidationFactory->get($type, $expression);
            $this->queued[$key] = $resolves;
            $this->queuedCount++;
          }
          catch (TypeUnsupportedException $e) {
            // Purgers got disabled after ::resolveType() ran, stop entirely.
            return $invalidations;
          }
          catch (InvalidExpressionException | MissingExpressionException | PluginException $e) {
            $this->logger->debug("skipping @expression: @message", [
              '@expression' => $expression,
              '@message' => $e->getMessage(),
            ]);
          }
        }
      }
    }
    return $invalidations;
  }

  /**
   * Build the invalidation expressions for one alias in one language.
   *
   * @param string $type
   *   The invalidation type, either 'url' or 'path'.
   * @param string $alias
   *   The alias, with leading slash.
   * @param string $langcode
   *   The langcode to generate the URL for.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   *
   * @return string[]
   *   The expressions, one per configured base URL. Empty when none could be
   *   generated.
   */
  protected function buildExpressions($type, $alias, $langcode, ImmutableConfig $config) {
    // Bases are the hosts to prefix the generated URL with. The unrouted URL
    // assembler ignores the 'base_url' option and always uses the host of the
    // current request, so prefixing is done here instead.
    $bases = ($type === 'url') ? $this->resolveBases($langcode, $config) : [''];
    $absolute = ($type === 'url') && ($bases === ['']);

    // 'path_processing' is off by default for unrouted URIs, which would skip
    // outbound path processing entirely and leave out the language prefix.
    $options = ['absolute' => $absolute, 'path_processing' => TRUE];
    if ($language = $this->languageManager->getLanguage($langcode)) {
      $options['language'] = $language;
    }

    try {
      // The 'base:' scheme assembles through the unrouted URL assembler, which
      // runs outbound path processors - so language prefixes get applied - but
      // performs no route validation. Route validation would throw on deletes,
      // where the alias no longer resolves.
      // The leading slash is kept: outbound processing hands the path to the
      // alias manager, which rejects paths that don't have one.
      $url = Url::fromUri('base:/' . ltrim($alias, '/'), $options);
      // ::toString(TRUE) returns a GeneratedUrl, which cannot trigger the
      // leaked-cacheability LogicException when an alias happens to be saved
      // inside an active render context.
      $generated = $url->toString(TRUE)->getGeneratedUrl();
    }
    catch (\Exception $e) {
      $this->logger->debug("cannot generate a URL for @alias: @message", [
        '@alias' => $alias,
        '@message' => $e->getMessage(),
      ]);
      return [];
    }

    // Path invalidations aren't allowed to start with a slash.
    if ($type !== 'url') {
      return [ltrim($this->relativize($generated), '/')];
    }

    $expressions = [];
    foreach ($bases as $base) {
      $expressions[] = ($base === '') ? $generated : $base . $this->relativize($generated);
    }
    return $expressions;
  }

  /**
   * Strip scheme and host off a generated URL.
   *
   * Outbound path processing can turn a relative URL into an absolute one -
   * language negotiation does so on some core versions - which would corrupt
   * the expression once a base URL gets prefixed onto it.
   *
   * @param string $url
   *   The generated URL, absolute or relative.
   *
   * @return string
   *   The path, query and fragment, with leading slash.
   */
  protected function relativize($url) {
    if (!is_array($parsed = parse_url($url)) || !isset($parsed['host'])) {
      return '/' . ltrim($url, '/');
    }
    $relative = '/' . ltrim($parsed['path'] ?? '', '/');
    if (isset($parsed['query'])) {
      $relative .= '?' . $parsed['query'];
    }
    if (isset($parsed['fragment'])) {
      $relative .= '#' . $parsed['fragment'];
    }
    return $relative;
  }

  /**
   * Resolve the base URLs to prefix generated URLs with.
   *
   * @param string $langcode
   *   The langcode the URL is generated for.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   *
   * @return string[]
   *   Base URLs without trailing slash, or [''] to generate an absolute URL
   *   from the host of the current request.
   */
  protected function resolveBases($langcode, ImmutableConfig $config) {
    $bases = [];
    foreach ((array) $config->get('base_urls') as $base) {
      if (is_string($base) && (($base = rtrim(trim($base), '/')) !== '')) {
        $bases[] = $base;
      }
    }
    if (count($bases)) {
      return array_values(array_unique($bases));
    }
    if (!is_null($domain = $this->resolveLanguageDomain($langcode))) {
      return [$domain];
    }
    return [''];
  }

  /**
   * Resolve the domain that language negotiation serves a language on.
   *
   * Domain based language negotiation sets the 'base_url' option during
   * outbound path processing, but the unrouted URL assembler doesn't act on
   * that option - so the negotiated domain is resolved here instead.
   *
   * @param string $langcode
   *   The langcode the URL is generated for.
   *
   * @return string|null
   *   The base URL for this language, or NULL when there is none.
   */
  protected function resolveLanguageDomain($langcode) {
    $negotiation = $this->configFactory->get('language.negotiation');
    if ($negotiation->get('url.source') !== 'domain') {
      return NULL;
    }
    $domains = $negotiation->get('url.domains');
    if (!is_array($domains) || empty($domains[$langcode]) || !is_string($domains[$langcode])) {
      return NULL;
    }
    $domain = rtrim(trim($domains[$langcode]), '/');
    // Core stores these as hostnames, optionally with a scheme and/or port.
    if (!preg_match('#^https?://#', $domain)) {
      $request = $this->requestStack->getCurrentRequest();
      // Without a request there is nothing to derive the scheme from and
      // https is the safer assumption for a domain fronted by a CDN.
      $scheme = (is_null($request) || $request->isSecure()) ? 'https' : 'http';
      $domain = $scheme . '://' . $domain;
    }
    return $domain;
  }

  /**
   * Resolve the invalidation type to queue with.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   *
   * @return string|null
   *   Either 'url', 'path', or NULL when no enabled purger supports these.
   */
  protected function resolveType(ImmutableConfig $config) {
    $supported = $this->purgePurgers->getTypes();
    $configured = $config->get('invalidation_type');
    if (!in_array($configured, ['auto', NULL], TRUE)) {
      return in_array($configured, $supported, TRUE) ? $configured : NULL;
    }
    foreach (self::TYPES as $candidate) {
      if (in_array($candidate, $supported, TRUE)) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * Read the alias off a path alias entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The path alias entity.
   *
   * @return string|null
   *   The alias with leading slash, or NULL when it isn't usable.
   */
  protected function readAlias(EntityInterface $entity) {
    if (!($entity instanceof FieldableEntityInterface) || !$entity->hasField('alias')) {
      return NULL;
    }
    $alias = $entity->get('alias')->value;
    return (is_string($alias) && ($alias !== '')) ? $alias : NULL;
  }

  /**
   * Get the unchanged entity as it was before the update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The updated path alias entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original entity, or NULL when unavailable.
   */
  protected function getOriginal(EntityInterface $entity) {
    // EntityInterface::getOriginal() only exists as of Drupal 11.2, whereas
    // the $original property is the only option on Drupal 10.2 up to 11.1.
    if (method_exists($entity, 'getOriginal')) {
      return $entity->getOriginal();
    }
    // Accessed dynamically: the property is set by the storage handler and
    // isn't declared on the entity classes.
    $original = $entity->{'original'} ?? NULL;
    return ($original instanceof EntityInterface) ? $original : NULL;
  }

  /**
   * Resolve the langcodes to generate URLs for.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The path alias entity.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   *
   * @return string[]
   *   The langcodes to generate URLs for.
   */
  protected function resolveLangcodes(EntityInterface $entity, ImmutableConfig $config) {
    $default = $this->languageManager->getDefaultLanguage()->getId();
    $undefined = [
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];

    $langcode = NULL;
    if (($entity instanceof FieldableEntityInterface) && $entity->hasField('langcode')) {
      $langcode = $entity->get('langcode')->value;
    }
    if (is_string($langcode) && ($langcode !== '') && !in_array($langcode, $undefined, TRUE)) {
      return [$langcode];
    }

    // Language-neutral aliases resolve under every language, which means every
    // language-prefixed variant of the URL needs invalidating.
    if (!$config->get('expand_undefined_langcode')) {
      return [$default];
    }
    $available = array_keys($this->languageManager->getLanguages());
    // Sites with many languages can narrow this down to the languages that are
    // actually served from the edge. Volume is capped by 'max_per_request'.
    $configured = [];
    foreach ((array) $config->get('expand_langcodes') as $langcode) {
      if (is_string($langcode) && in_array($langcode, $available, TRUE)) {
        $configured[] = $langcode;
      }
    }
    return count($configured) ? array_values(array_unique($configured)) : $available;
  }

}
