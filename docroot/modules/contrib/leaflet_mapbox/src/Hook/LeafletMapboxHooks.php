<?php

namespace Drupal\leaflet_mapbox\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for leaflet_mapbox.
 */
class LeafletMapboxHooks {

  /**
   * The map id used when the module only supported a single map.
   */
  const LEGACY_MAP_KEY = 'default';

  /**
   * Constructs a LeafletMapboxHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_leaflet_map_info().
   */
  #[Hook('leaflet_map_info')]
  public function leafletMapInfo() {
    $config = $this->configFactory->get('leaflet_mapbox.settings');
    $maps = $config->get('maps');

    // Legacy single-map structure: sites that have not run update.php yet
    // still have the flat configuration keys. Expose them as the default map
    // so existing maps keep working until the update hook migrates them.
    if ($maps === NULL && $config->get('label') !== NULL) {
      $maps = [
        static::LEGACY_MAP_KEY => [
          'label' => $config->get('label'),
          'api_version' => $config->get('api_version'),
          'code' => $config->get('code'),
          'style_url' => $config->get('style_url'),
          'token' => $config->get('token'),
          'zoomlevel' => $config->get('zoomlevel'),
          'description' => $config->get('description'),
        ],
      ];
    }

    $map_info = [];
    foreach ($maps ?? [] as $key => $map) {
      $settings = [
        'attributionControl' => TRUE,
        'closePopupOnClick' => TRUE,
        'doubleClickZoom' => TRUE,
        'dragging' => TRUE,
        'fadeAnimation' => TRUE,
        'layerControl' => FALSE,
        'maxZoom' => 19,
        'minZoom' => 0,
        'scrollWheelZoom' => TRUE,
        'touchZoom' => TRUE,
        'trackResize' => TRUE,
        'zoom' => $map['zoomlevel'] ?? 2,
        'zoomAnimation' => TRUE,
        'zoomControl' => TRUE,
        'worldCopyJump' => TRUE,
      ];

      // Get access token.
      $token = $map['token'] ?? '';

      $options = ['attribution' => ''];
      $url_template = '';

      switch ($map['api_version'] ?? '4') {
        case '3':
          $code = $map['code'] ?? '';
          // Build urlTemplate.
          $url_template = "//{s}.tiles.mapbox.com/v4/$code/{z}/{x}/{y}.png?access_token=$token";
          break;

        case '4':
          // Extract username and styleid from style url.
          $style_url = $map['style_url'] ?? '';
          preg_match('/^mapbox:\/\/styles\/(\S*)\/(\S*)$/', $style_url, $matches);
          if (count($matches)) {
            $username = $matches[1];
            $styleid = $matches[2];
            // Build urlTemplate.
            $url_template = "//api.mapbox.com/styles/v1/$username/$styleid/tiles/{z}/{x}/{y}?access_token=$token";
          }

          // Mapbox v4 tiles are 512px by 512px and offset by 1 zoom level.
          $options += [
            'tileSize' => 512,
            'zoomOffset' => -1,
          ];
          break;
      }

      // The map migrated from the single-map configuration keeps the original
      // 'leaflet-mapbox' id so existing views and field displays keep working.
      $map_id = $key === static::LEGACY_MAP_KEY ? 'leaflet-mapbox' : 'leaflet-mapbox-' . $key;
      $map_info[$map_id] = [
        'label' => $map['label'] ?? '',
        'description' => $map['description'] ?? '',
        'settings' => $settings,
        'layers' => [
          'earth' => [
            'urlTemplate' => $url_template,
            'options' => $options,
          ],
        ],
      ];
    }

    return $map_info;
  }

}
