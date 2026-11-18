# Leaflet Mapbox

Leaflet integration with the Mapbox tile platform.

With this module you will be able to use your own Mapbox map tiles in your
map views through the [Leaflet module](https://www.drupal.org/project/leaflet).

- For a full description of the module, visit the
  [project page](https://www.drupal.org/project/leaflet_mapbox).
- To submit bug reports and feature suggestions, or to track changes, use the
  [issue queue](https://www.drupal.org/project/issues/leaflet_mapbox).

## Requirements

This module requires the following module:

- [Leaflet](https://www.drupal.org/project/leaflet)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

1. Go to the module configuration page:
   *Configuration > Web Services > Leaflet Mapbox*.
2. Click *Add another map* to create a map (you can configure as many maps as
   you need — each becomes a separate map option for Leaflet).
3. Give your map a label.
4. Select the API version. API 4 is recommended.
5. Copy and paste the Style URL from mapbox.com.
6. Copy and paste your access token from mapbox.com.
7. Add a zoom level and description, then save.
8. You should now be able to use your new Mapbox style(s) when creating
   Leaflet views.

Sites upgrading from an earlier release keep working without changes: the
update hook migrates the previous single-map settings, and that map keeps its
original `leaflet-mapbox` machine id, so existing views and field displays do
not need to be reconfigured.
