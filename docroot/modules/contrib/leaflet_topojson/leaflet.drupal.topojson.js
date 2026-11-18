(function ($) {

  /**
   * Override Leaflet's create_geometry method to add topojson.
   *
   * @param feature
   *   The feature definition coming from Drupal backend.
   * @param map_settings
   *   The map_settings if defined, false otherwise..
   *
   * @returns {*}
   *   The generated Leaflet Geometry.
   */
    Drupal.Leaflet.prototype.create_geometry = function(feature, map_settings = false) {
      let lFeature;
      switch (feature.type) {
        case 'point':
          lFeature = this.create_point(feature);
          break;

        case 'linestring':
          lFeature = this.create_linestring(feature, map_settings ? map_settings['leaflet_markercluster']['include_path'] : false);
          break;

        case 'polygon':
          lFeature = this.create_polygon(feature, map_settings ? map_settings['leaflet_markercluster']['include_path'] : false);
          break;

        case 'multipolygon':
          lFeature = this.create_multipolygon(feature, map_settings ? map_settings['leaflet_markercluster']['include_path'] : false);
          break;

        case 'multipolyline':
          lFeature = this.create_multipoly(feature, map_settings ? map_settings['leaflet_markercluster']['include_path'] : false);
          break;

        // In case of singular cases where feature.type is json we use this.create_json method.
        // @see https://www.drupal.org/project/leaflet/issues/3377403
        // @see https://www.drupal.org/project/leaflet/issues/3186029
        case 'json':
          lFeature = this.create_json(feature.json, feature.events);
          break;

        case 'topojson':
          lFeature = this.create_topojson(feature.json);
          break;

        case 'multipoint':
        case 'geometrycollection':
          lFeature = this.create_collection(feature);
          break;

        default:
          lFeature = {};
      }
      return lFeature;
    }

  /**
   * Add a new TopoJSON method to the leaflet object.
   */
  Drupal.Leaflet.prototype.create_topojson = function (json) {
    var lJSON = new L.TopoJSON();
    lJSON.on('featureparse', function (e) {
      e.layer.bindPopup(e.properties.popup);

      for (var layer_id in e.layer._layers) {
        for (var i in e.layer._layers[layer_id]._latlngs) {
          Drupal.Leaflet.bounds.push(e.layer._layers[layer_id]._latlngs[i]);
        }
      }

      if (e.properties.style) {
        e.layer.setStyle(e.properties.style);
      }

      if (e.properties.leaflet_id) {
        e.layer._leaflet_id = e.properties.leaflet_id;
      }
    });

    lJSON.addData(json);
    return lJSON;
  };

})(jQuery);