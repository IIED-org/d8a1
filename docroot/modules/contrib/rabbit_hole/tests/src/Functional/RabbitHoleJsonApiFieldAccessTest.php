<?php

namespace Drupal\Tests\rabbit_hole\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Tests that JSON:API cannot be used to bypass the Rabbit Hole permission.
 *
 * The node form only offers the Rabbit Hole settings to users holding the
 * "rabbit hole administer node" permission. JSON:API honours field access
 * rather than the form, so without field access an editor with only
 * "edit any <type> content" could turn a node into an open redirect or hide it.
 *
 * @see rabbit_hole_entity_field_access()
 *
 * @group rabbit_hole
 */
class RabbitHoleJsonApiFieldAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'rabbit_hole',
    'rh_node',
    'jsonapi',
    'basic_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node being patched.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Core ships JSON:API read-only; the reported bypass needs writes.
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    // JSON:API registers its routes per bundle.
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('rabbit_hole.behavior_settings_manager')
      ->saveBehaviorSettings([
        'action' => 'display_page',
        'allow_override' => TRUE,
      ], 'node_type', 'page');

    $this->node = Node::create(['type' => 'page', 'title' => 'Test']);
    $this->node->save();
  }

  /**
   * Sends a JSON:API PATCH request setting a redirect on the node.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account making the request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function patchRedirect($account) {
    $url = Url::fromRoute('jsonapi.node--page.individual', ['entity' => $this->node->uuid()])->setAbsolute();
    return $this->getHttpClient()->request('PATCH', $url->toString(), [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::AUTH => [$account->getAccountName(), $account->passRaw],
      RequestOptions::HEADERS => [
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
      ],
      RequestOptions::BODY => json_encode([
        'data' => [
          'type' => 'node--page',
          'id' => $this->node->uuid(),
          'attributes' => [
            'rh_action' => 'page_redirect',
            'rh_redirect' => 'https://example.com/',
            'rh_redirect_response' => 301,
          ],
        ],
      ]),
    ]);
  }

  /**
   * Reloads the node from storage.
   */
  protected function reloadNode() {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$this->node->id()]);
    return Node::load($this->node->id());
  }

  /**
   * An editor without the administer permission is refused.
   */
  public function testEditorIsRefused(): void {
    $editor = $this->drupalCreateUser(['access content', 'edit any page content']);

    $response = $this->patchRedirect($editor);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('rh_action', (string) $response->getBody());

    $node = $this->reloadNode();
    $this->assertNotSame('page_redirect', $node->get('rh_action')->value);
    $this->assertNull($node->get('rh_redirect')->value);

    // The node is still displayed rather than redirected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals($node->toUrl());
  }

  /**
   * A holder of the administer permission can set the redirect.
   */
  public function testAdministratorIsAllowed(): void {
    $administrator = $this->drupalCreateUser([
      'access content',
      'edit any page content',
      'rabbit hole administer node',
    ]);

    $response = $this->patchRedirect($administrator);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

    $node = $this->reloadNode();
    $this->assertSame('page_redirect', $node->get('rh_action')->value);
    $this->assertSame('https://example.com/', $node->get('rh_redirect')->value);
  }

}
