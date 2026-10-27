<?php

namespace Drupal\Tests\rabbit_hole\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that Rabbit Hole settings are kept off confirmation forms.
 *
 * The Trash module registers "purge" and "restore" entity forms that extend
 * ContentEntityConfirmFormBase. Attaching the Rabbit Hole settings to those
 * replaces the confirm button's own submit handler and breaks the operation.
 *
 * @see rabbit_hole_form_alter()
 * @see https://www.drupal.org/project/rabbit_hole/issues/3575535
 *
 * @group rabbit_hole
 */
class FormAlterConfirmFormTest extends KernelTestBase {

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
    'rabbit_hole_form_test',
  ];

  /**
   * The node the forms are built for.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The form object under test.
   *
   * @var \Drupal\Core\Entity\EntityFormInterface
   */
  protected $formObject;

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

    \Drupal::service('rabbit_hole.behavior_settings_manager')
      ->saveBehaviorSettings([
        'action' => 'access_denied',
        'allow_override' => TRUE,
      ], 'node_type', 'page');

    $this->node = Node::create(['type' => 'page', 'title' => 'Test']);
    $this->node->save();

    // The root user bypasses the "rabbit hole administer node" permission.
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * The settings are attached to the regular node edit form.
   */
  public function testSettingsOnEditForm(): void {
    $form = $this->buildForm('edit');

    $this->assertArrayHasKey('rabbit_hole', $form);
    $this->assertContains('_rabbit_hole_general_form_submit', $form['actions']['submit']['#submit']);
  }

  /**
   * The settings are kept off confirmation forms, whatever the operation.
   */
  public function testSettingsNotOnConfirmForm(): void {
    $form = $this->buildForm('rabbit_hole_test_confirm');

    $this->assertArrayNotHasKey('rabbit_hole', $form);
    $this->assertSame([[$this->formObject, 'submitForm']], $form['actions']['submit']['#submit']);
  }

  /**
   * The settings are kept off operations excluded by name.
   */
  public function testSettingsNotOnDisallowedOperation(): void {
    $form = $this->buildForm('purge');

    $this->assertArrayNotHasKey('rabbit_hole', $form);
    $this->assertNotContains('_rabbit_hole_general_form_submit', $form['actions']['submit']['#submit']);
  }

  /**
   * Builds the node form for the given operation.
   *
   * @param string $operation
   *   The entity form operation.
   *
   * @return array
   *   The built form.
   */
  protected function buildForm(string $operation): array {
    $this->formObject = \Drupal::entityTypeManager()
      ->getFormObject('node', $operation);
    $this->formObject->setEntity($this->node);

    return \Drupal::formBuilder()->getForm($this->formObject);
  }

}
