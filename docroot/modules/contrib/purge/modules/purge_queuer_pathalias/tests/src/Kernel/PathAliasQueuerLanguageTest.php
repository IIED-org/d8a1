<?php

namespace Drupal\Tests\purge_queuer_pathalias\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\purge_queuer_pathalias\PathAliasQueuer;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how \Drupal\purge_queuer_pathalias\PathAliasQueuer handles languages.
 *
 * @coversDefaultClass \Drupal\purge_queuer_pathalias\PathAliasQueuer
 * @group purge
 */
#[CoversClass(PathAliasQueuer::class)]
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class PathAliasQueuerLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
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
    $this->installConfig(['purge_queuer_pathalias', 'language']);
    ConfigurableLanguage::createFromLangcode('nl')->save();
    // Enable URL language negotiation, so that outbound path processing runs.
    $this->config('language.types')
      ->set('negotiation.language_url.enabled', ['language-url' => 0])
      ->save();
    // Core only registers 'path_processor_language' when the container gets
    // built for a multilingual site, so it has to be rebuilt now that a second
    // language exists.
    $this->container->get('kernel')->rebuildContainer();
    $this->container = \Drupal::getContainer();
    $this->initializeQueuersService(['pathalias']);
    $this->initializePurgersService(['good']);
    $this->initializeQueueService();
  }

  /**
   * Configure domain based language negotiation.
   *
   * @param array $domains
   *   Associative array of langcodes and the domains serving them.
   */
  protected function setLanguageDomains(array $domains): void {
    $this->config('language.negotiation')
      ->set('url.source', 'domain')
      ->set('url.domains', $domains)
      ->save();
  }

  /**
   * Create and save a path alias entity.
   *
   * @param string $alias
   *   The alias, with leading slash.
   * @param string $langcode
   *   The langcode of the alias.
   */
  protected function createAlias($alias, $langcode): void {
    $this->container->get('entity_type.manager')
      ->getStorage('path_alias')
      ->create([
        'path' => '/user/1',
        'alias' => $alias,
        'langcode' => $langcode,
      ])
      ->save();
  }

  /**
   * Commit the buffer and return all queued expressions.
   *
   * @return string[]
   *   List of expressions, sorted alphabetically.
   */
  protected function claimQueued(): array {
    $this->container->get('purge.queue')->commit();
    $queued = [];
    foreach ((array) $this->purgeQueue->claim(100) as $invalidation) {
      $queued[] = $invalidation->getExpression();
    }
    sort($queued);
    return $queued;
  }

  /**
   * Tests that domain based language negotiation determines the host.
   *
   * The unrouted URL assembler doesn't act on the 'base_url' option that
   * language negotiation sets during outbound path processing, so the queuer
   * resolves the negotiated domain itself.
   */
  public function testDomainNegotiationDeterminesHost(): void {
    $this->setLanguageDomains([
      'en' => 'en.example.com',
      'nl' => 'nl.example.com',
    ]);
    $this->createAlias('/dutch-alias', 'nl');
    $this->createAlias('/english-alias', 'en');
    $this->assertSame([
      'http://en.example.com/english-alias',
      'http://nl.example.com/dutch-alias',
    ], $this->claimQueued());
  }

  /**
   * Tests that a language without a configured domain falls back.
   */
  public function testDomainNegotiationWithoutDomainForLanguage(): void {
    $this->setLanguageDomains(['en' => 'en.example.com']);
    $this->createAlias('/dutch-alias', 'nl');
    $this->assertSame(['http://localhost/dutch-alias'], $this->claimQueued());
  }

  /**
   * Tests that configured base URLs win over negotiated domains.
   */
  public function testBaseUrlsOverrideDomainNegotiation(): void {
    $this->setLanguageDomains([
      'en' => 'en.example.com',
      'nl' => 'nl.example.com',
    ]);
    $this->config('purge_queuer_pathalias.settings')
      ->set('base_urls', ['https://cdn.example.com'])
      ->save();
    $this->createAlias('/dutch-alias', 'nl');
    $this->assertSame(['https://cdn.example.com/dutch-alias'], $this->claimQueued());
  }

  /**
   * Tests that prefix based negotiation still yields prefixed URLs.
   */
  public function testPrefixNegotiation(): void {
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'nl' => 'nl'])
      ->save();
    $this->createAlias('/dutch-alias', 'nl');
    $this->assertSame(['http://localhost/nl/dutch-alias'], $this->claimQueued());
  }

  /**
   * Tests that language neutral aliases expand to every language.
   */
  public function testUndefinedLangcodeExpandsToEveryDomain(): void {
    $this->setLanguageDomains([
      'en' => 'en.example.com',
      'nl' => 'nl.example.com',
    ]);
    $this->createAlias('/neutral-alias', LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertSame([
      'http://en.example.com/neutral-alias',
      'http://nl.example.com/neutral-alias',
    ], $this->claimQueued());
  }

  /**
   * Tests that the expansion can be limited to configured languages.
   */
  public function testExpandLangcodesLimitsTheExpansion(): void {
    $this->setLanguageDomains([
      'en' => 'en.example.com',
      'nl' => 'nl.example.com',
    ]);
    $this->config('purge_queuer_pathalias.settings')
      ->set('expand_langcodes', ['nl'])
      ->save();
    $this->createAlias('/neutral-alias', LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertSame(['http://nl.example.com/neutral-alias'], $this->claimQueued());
  }

  /**
   * Tests that langcodes of removed languages are ignored.
   */
  public function testExpandLangcodesIgnoresUnknownLanguages(): void {
    $this->setLanguageDomains([
      'en' => 'en.example.com',
      'nl' => 'nl.example.com',
    ]);
    $this->config('purge_queuer_pathalias.settings')
      ->set('expand_langcodes', ['de'])
      ->save();
    $this->createAlias('/neutral-alias', LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertSame([
      'http://en.example.com/neutral-alias',
      'http://nl.example.com/neutral-alias',
    ], $this->claimQueued());
  }

}
