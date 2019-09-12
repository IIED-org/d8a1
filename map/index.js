import 'ol/ol.css';
import {Map, View} from 'ol';
import OSM from 'ol/source/OSM';
import TileLayer from 'ol/layer/Tile.js';
import XYZ from 'ol/source/XYZ.js';
import GeoJSON from 'ol/format/GeoJSON.js';
import VectorLayer from 'ol/layer/Vector.js';
import VectorSource from 'ol/source/Vector.js';
import {Fill, Stroke, Style, Text} from 'ol/style.js';

var key = 'pk.eyJ1IjoiaWllZCIsImEiOiJjazAzcHVtMmcyYXZ5M2xvMXFlaWk2dWh3In0.cSVpPstX-AU9kPBR47kQuA';

var countryLayer = new VectorLayer({
   source: new VectorSource({
     url: 'https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json',
     format: new GeoJSON()
   }),
   style: function(feature) {
      style.getText().setText();
      return style;
    }
 });

 var style = new Style({
        fill: new Fill({
          color: 'rgba(255, 255, 255, 0)'
        }),
        stroke: new Stroke({
          color: 'rgba(255, 255, 255, 0)',
          width: 1
        }),
        text: new Text({
          font: '12px Lato',
          fill: new Fill({
            color: '#000'
          })
        })
      });

const map = new Map({
  target: 'map',
  layers: [
    new TileLayer({
          source: new XYZ({
            attributions: '© <a href="https://www.mapbox.com/map-feedback/">Mapbox</a> ' +
              '© <a href="https://www.openstreetmap.org/copyright">' +
              'OpenStreetMap contributors</a>',
            url: 'https://api.mapbox.com/styles/v1/iied/ck03pwvo2006v1co70ojs0oz2/tiles/256/' +
                '{z}/{x}/{y}?access_token=' + key,
          })
        }),
    countryLayer
  ],
    view: new View({
    center: [0, 0],
    zoom: 2
  })
});

var highlightStyle = new Style({
        stroke: new Stroke({
          color: 'rgba(0,0,0,0.25)',
          width: 1
        }),
        fill: new Fill({
          color: 'rgba(255,255,255,0.25)'
        }),
        text: new Text({
          font: '12px Lato',
          fill: new Fill({
            color: '#555'
          })
        })
      });

  var featureOverlay = new VectorLayer({
        source: new VectorSource(),
        map: map,
        style: function(feature) {
          highlightStyle.getText().setText(feature.getId());
          return highlightStyle;
        }
      });

      var highlight;
      var displayFeatureInfo = function(pixel) {

        var feature = map.forEachFeatureAtPixel(pixel, function(feature) {
          return feature;
        });

        var info = document.getElementById('info');
        if (feature) {
          info.innerHTML = feature.getId() + ': ' + feature.get('name');
        } else {
          info.innerHTML = '&nbsp;';
        }

        if (feature !== highlight) {
          if (highlight) {
            featureOverlay.getSource().removeFeature(highlight);
          }
          if (feature) {
            featureOverlay.getSource().addFeature(feature);
          }
          highlight = feature;
        }

      };

      map.on('pointermove', function(evt) {
        if (evt.dragging) {
          return;
        }
        var pixel = map.getEventPixel(evt.originalEvent);
        displayFeatureInfo(pixel);
      });

      map.on('click', function(evt) {
        displayFeatureInfo(evt.pixel);
      });
  //
  // var dataOverlay = new VectorLayer({
  //       source: new VectorSource({
  //         url: 'http://pnp.dev.dd:8083/sites/default/files/data.json',
  //         format: new GeoJSON()
  //       }),
  //       map: map,
  //       style: function(data) {
  //         highlightStyle.getText().setText(data.get("name"));
  //         return highlightStyle;
  //       }
  //     });
  //
  //     var highlight;
  //     var displayDataInfo = function(datapixel) {
  //
  //       var data = map.forEachFeatureAtPixel(datapixel, function(data) {
  //         return data;
  //       });
  //
  //       var nodes = document.getElementById('data');
  //       if (nodes) {
  //         nodes.innerHTML = data.get("country_code") + ': ' + data.get('name');
  //       } else {
  //         nodes.innerHTML = '&nbsp;';
  //       }
  //
  //       if (data !== highlight) {
  //         if (highlight) {
  //           dataOverlay.getSource().removeFeature(highlight);
  //         }
  //         if (data) {
  //           dataOverlay.getSource().addFeature(data);
  //         }
  //         highlight = data;
  //       }
  //
  //     };
  //
  //     map.on('pointermove', function(evt) {
  //       if (evt.dragging) {
  //         return;
  //       }
  //       var datapixel = map.getEventPixel(evt.originalEvent);
  //       displayDataInfo(datapixel);
  //     });
  //
  //     map.on('click', function(evt) {
  //       displayDataInfo(evt.datapixel);
  //     });
