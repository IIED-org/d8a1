<?php

namespace Drupal\Tests\rabbit_hole\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests field access to the Rabbit Hole fields.
 *
 * The fields are only attached to the entity form for users holding the
 * "rabbit hole administer <entity type>" permission. Field access has to
 * enforce the same rule, or any writer that honours field access but not the
 * form - JSON:API, REST, bulk operations - lets an ordinary editor redirect
 * or hide an entity.
 *
 * @see rabbit_hole_entity_field_access()
 *
 * @group rabbit_hole
 */
class RabbitHoleFieldAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'rabbit_hole',
    'rh_node',
  ];

  /**
   * The node whose fields are checked.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Take uid 0 and uid 1 so that no test user is the superuser, who
    // bypasses every permission check.
    $this->createUser([], NULL, FALSE, ['uid' => 0]);
    $this->createUser([], NULL, FALSE, ['uid' => 1]);

    $this->node = Node::create(['type' => 'page', 'title' => 'Test']);
    $this->node->save();
  }

  /**
   * The Rabbit Hole field names added to nodes.
   *
   * @return string[]
   *   The field names.
   */
  protected function rabbitHoleFieldNames(): array {
    $names = array_keys(\Drupal::service('rabbit_hole.entity_extender')->getRabbitHoleFields('node'));
    $this->assertContains('rh_action', $names);
    $this->assertContains('rh_redirect', $names);
    return $names;
  }

  /**
   * Editors without the administer permission cannot edit the fields.
   */
  public function testEditorCannotEditRabbitHoleFields(): void {
    $editor = $this->createUser(['access content', 'edit any page content']);

    $this->assertTrue($this->node->access('update', $editor));
    $this->assertTrue($this->node->get('title')->access('edit', $editor));
    foreach ($this->rabbitHoleFieldNames() as $name) {
      $this->assertFalse($this->node->get($name)->access('edit', $editor), "$name is not editable without the administer permission.");
      $this->assertTrue($this->node->get($name)->access('view', $editor), "$name is still viewable.");
    }
  }

  /**
   * Holders of the administer permission can edit the fields.
   */
  public function testAdministratorCanEditRabbitHoleFields(): void {
    $administrator = $this->createUser([
      'access content',
      'edit any page content',
      'rabbit hole administer node',
    ]);

    foreach ($this->rabbitHoleFieldNames() as $name) {
      $this->assertTrue($this->node->get($name)->access('edit', $administrator), "$name is editable with the administer permission.");
    }
  }

}
