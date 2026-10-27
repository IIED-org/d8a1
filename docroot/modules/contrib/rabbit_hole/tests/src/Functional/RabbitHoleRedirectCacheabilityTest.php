<?php

namespace Drupal\Tests\rabbit_hole\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that Rabbit Hole redirects are served with cache control headers.
 *
 * @see https://www.drupal.org/project/rabbit_hole/issues/2991799
 *
 * @group rabbit_hole
 */
class RabbitHoleRedirectCacheabilityTest extends BrowserTestBase {

  /**
   * The page cache maximum age used throughout this test.
   */
  const PAGE_MAX_AGE = 300;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['rh_node', 'node'];

  /**
   * The content type used for the test entities.
   *
   * @var string
   */
  protected $contentType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contentType = $this->drupalCreateContentType()->id();
    $this->container->get('rabbit_hole.behavior_settings_manager')
      ->saveBehaviorSettings([
        'action' => 'display_page',
        'allow_override' => TRUE,
      ], 'node_type', $this->contentType);

    $this->config('system.performance')
      ->set('cache.page.max_age', self::PAGE_MAX_AGE)
      ->save();
  }

  /**
   * Tests that every redirect code is served with cache control headers.
   */
  public function testRedirectCacheControl() {
    foreach ([301, 302, 303, 304, 305, 307] as $redirect_code) {
      $node = $this->drupalCreateNode([
        'type' => $this->contentType,
        'rh_action' => 'page_redirect',
        'rh_redirect' => '/node',
        'rh_redirect_response' => $redirect_code,
      ]);

      $response = $this->getRedirectResponse($node);

      $this->assertEquals($redirect_code, $response->getStatusCode());
      // Anonymous redirects have to be cacheable by proxies and browsers for
      // the configured page cache maximum age. Without this a browser is free
      // to make up its own caching rules for the redirect.
      $this->assertEquals('max-age=' . self::PAGE_MAX_AGE . ', public', $response->getHeaderLine('Cache-Control'), 'Cache-Control header of the ' . $redirect_code . ' redirect.');
    }
  }

  /**
   * Tests that redirects carry the cacheability they were built from.
   */
  public function testRedirectCacheableMetadata() {
    $node = $this->drupalCreateNode([
      'type' => $this->contentType,
      'rh_action' => 'page_redirect',
      'rh_redirect' => '/node',
      'rh_redirect_response' => 301,
    ]);

    $response = $this->getRedirectResponse($node);

    $tags = explode(' ', $response->getHeaderLine('X-Drupal-Cache-Tags'));
    $contexts = explode(' ', $response->getHeaderLine('X-Drupal-Cache-Contexts'));

    // The redirect is derived from the entity and from the bundle settings, so
    // a change to either one has to invalidate it.
    $this->assertContains('node:' . $node->id(), $tags);
    $this->assertContains('config:rabbit_hole.behavior_settings.node_type_' . $this->contentType, $tags);
    // The "bypass" permission decides whether to redirect at all.
    $this->assertContains('user.permissions', $contexts);
  }

  /**
   * Requests an entity without following the redirect.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The raw response.
   */
  protected function getRedirectResponse($entity) {
    return $this->getHttpClient()->request('GET', $entity->toUrl()->setAbsolute()->toString(), [
      'allow_redirects' => FALSE,
      'http_errors' => FALSE,
    ]);
  }

}
