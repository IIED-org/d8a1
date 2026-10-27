<?php

namespace Drupal\Tests\rabbit_hole\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the post update that normalizes the default behavior settings.
 *
 * @group rabbit_hole
 *
 * @see rabbit_hole_post_update_save_default_behavior_settings()
 */
class BehaviorSettingsDefaultPostUpdateTest extends KernelTestBase {

  /**
   * The default behavior settings shipped by the module.
   */
  const DEFAULT_SETTINGS = [
    'default',
    'default_bundle',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'rabbit_hole',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['rabbit_hole']);
    require_once __DIR__ . '/../../../rabbit_hole.post_update.php';
  }

  /**
   * Tests that the default behavior settings get the missing keys back.
   */
  public function testDefaultBehaviorSettingsAreSaved() {
    $config_factory = $this->container->get('config.factory');

    // Emulate a site that installed Rabbit Hole before entity_type_id and
    // entity_id were added to the behavior settings entity.
    foreach (self::DEFAULT_SETTINGS as $id) {
      $name = 'rabbit_hole.behavior_settings.' . $id;
      $config = $config_factory->getEditable($name);
      $data = $config->getRawData();
      unset($data['entity_type_id'], $data['entity_id']);
      $config->setData($data)->save();

      $this->assertArrayNotHasKey('entity_type_id', $config_factory->get($name)->getRawData());
    }
    $this->container->get('entity_type.manager')
      ->getStorage('behavior_settings')
      ->resetCache();

    rabbit_hole_post_update_save_default_behavior_settings();

    foreach (self::DEFAULT_SETTINGS as $id) {
      $data = $config_factory->get('rabbit_hole.behavior_settings.' . $id)->getRawData();
      $this->assertArrayHasKey('entity_type_id', $data);
      $this->assertArrayHasKey('entity_id', $data);
      $this->assertNull($data['entity_type_id']);
      $this->assertNull($data['entity_id']);
      $this->assertSame($id, $data['id']);
      $this->assertSame('display_page', $data['action']);
    }
  }

  /**
   * Tests that the post update does not create missing behavior settings.
   */
  public function testMissingDefaultBehaviorSettingsAreSkipped() {
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('rabbit_hole.behavior_settings.default_bundle')
      ->delete();
    $this->container->get('entity_type.manager')
      ->getStorage('behavior_settings')
      ->resetCache();

    rabbit_hole_post_update_save_default_behavior_settings();

    $this->assertTrue($config_factory->get('rabbit_hole.behavior_settings.default_bundle')->isNew());
    $this->assertFalse($config_factory->get('rabbit_hole.behavior_settings.default')->isNew());
  }

}
