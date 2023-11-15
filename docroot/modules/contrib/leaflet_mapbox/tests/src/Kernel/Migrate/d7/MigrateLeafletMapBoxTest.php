<?php
/**
 * @file
 * Contains \Drupal\Tests\leaflet_mapbox\Kernel\Migrate\d7\MigrateLeafletMapBoxTest.php.
 */

namespace Drupal\Tests\leaflet_mapbox\Kernel\Migrate\d7;


use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

class MigrateLeafletMapBoxTest extends MigrateDrupal7TestBase {
  /**
   * @inheritDoc
   */
  protected function setUp() {
    parent::setUp();
    $this->executeMigrations('d7_leaflet_mapbox_settings');
  }

  // TODO Fixtures + Tests
  public function testConfigurationMigration() {

  }
}
