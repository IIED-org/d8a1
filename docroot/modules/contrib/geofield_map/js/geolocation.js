/**
 * @file
 * Javascript for the Geolocation in Geofield Map.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.geofieldMapGeolocation = {
    attach: function (context, settings) {

      let first_geofield_map = 0;

      // Trigger the HTML5 Geocoding only if not in the Geofield Field
      // configuration page.
      if ($(context).find('#edit-field-geofield-wrapper').length && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(updateLocation, errorUpdateLocation);
      }

      // Update Location for each Geofield Map based on the HTML5 Geolocation
      // Position of the user.
      function updateLocation(position) {

        const geofield_maps_array = Object.entries(settings['geofield_map']).reverse();
        for (const [mapid, options] of geofield_maps_array) {
          if (mapid.includes('-0-value')) {
            first_geofield_map = 1;
          }
          else {
            first_geofield_map = 0;
          }
          if (options.geolocation) {
            updateGeoLocationFields($('#' + mapid, context).parents('.geofieldmap-widget-auto-geocode'), position, options);
          }
        }

        // Bind the "updateGeoLocationFields" click eent to the
        // "geofield-html5-geocode-button" button.
        $('input[name="geofield-html5-geocode-button"]').once('geofield_geolocation').click(function (e) {
          e.preventDefault();
          updateGeoLocationFields($(this).parents('.geofieldmap-widget-auto-geocode').parent(), position, []);
        });

      }

      // Update Geolocation Fields based on the user position,.
      function updateGeoLocationFields(fields, position, options) {
        if (options.length === 0 || (options['lat'] === 0 && options['lng'] === 0 && first_geofield_map === 1)) {
          fields.find('.geofield-lat').val(position.coords.latitude.toFixed(6)).trigger('change');
          fields.find('.geofield-lon').val(position.coords.longitude.toFixed(6)).trigger('change');
        }
      }

      // Error callback for getCurrentPosition.
      function errorUpdateLocation() {
        /* eslint-disable no-console */
        console.log('Couldn\'t find any HTML5 position');
        /* eslint-enable no-console */
      }
    }
  };
})(jQuery, Drupal);
