<?php

namespace Drupal\leaflet_countries\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\leaflet_countries\Countries;
use Drupal\leaflet_views\Plugin\views\style\LeafletMap;
use Drupal\views\ResultRow;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "leaflet_countries_map",
 *   title = @Translation("Leaflet Countries Map"),
 *   help = @Translation("Displays a View as a Leaflet countries map."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class LeafletCountriesMap extends LeafletMap implements ContainerFactoryPluginInterface {

  /**
   * Get a list of fields and a sublist of geo data fields in this view.
   *
   * @return array
   *   Available data sources.
   */
  protected function getAvailableDataSources() {
    $fields_geo_data = [];

    /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\FieldPluginBase')) {
        /** @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        }
        catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        if (array_key_exists($handler->definition['field_name'], $field_storage_definitions)) {
          $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];
          $type = $field_storage_definition->getType();
          $field_name = $handler->definition['field_name'];
          try {
            $definition = $this->fieldTypeManager->getDefinition($type);
            if (
              is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\AddressItem', TRUE) ||
              is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\CountryItem', TRUE) ||
              is_a($definition['class'], '\Drupal\leaflet_countries\Plugin\Field\FieldType\LeafletCountryItem', TRUE)
            ) {
              $fields_geo_data[$field_name] = $label;
            }
          }
          catch (\Exception $e) {
            watchdog_exception("Leaflet Countries Map - Get Available data sources", $e);
          }
        }
      }
    }

    return $fields_geo_data;
  }

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = [
      'leaflet_country_map' => [
        'default' => [
          'description_field' => '',
          'view_mode' => 'teaser',
          'linecolor' => '666666',
          'lineweight' => '1.5',
          'lineopacity' => '1',
          'fillopacity' => '1',
          'fillcolor' => 'CCCCCC',
          'name_trigger_popup' => TRUE,
          'onclick_href' => FALSE,
          'onclick_href_pattern' => '/explore',
        ],
      ],
    ] + parent::defineOptions();

    $options['map_position']['default']['zoom'] = 2;
    $options['map_position']['default']['minZoom'] = 2;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields_geo_data = $this->getAvailableDataSources();
    if (!count($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one country field to the view and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['leaflet-warning'],
        ],
        '#weight' => -10,
      ];
      return;
    }
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which country fields contains geodata you want to map?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
      '#multiple' => TRUE,
      '#size' => count($fields_geo_data),
      '#weight' => -10,
    ];

    $form['grouping']['#weight'] = -9;

    $form['leaflet_country_map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaflet Countries Map'),
      '#weight' => -8,
    ];
    $form['leaflet_country_map']['group_by_country'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group by country'),
      '#description' => $this->t('Group all entities by country, for example to allow a single popup per country, listing the entities within that country.'),
      '#default_value' => $this->options['leaflet_country_map']['group_by_country'],
    ];
    $form['leaflet_country_map']['country_name_tooltip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display country name as tool tip.'),
      '#description' => $this->t('Show the country name as the tool tip.'),
      '#default_value' => $this->options['leaflet_country_map']['country_name_tooltip'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ],
    ];
    $form['leaflet_country_map']['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field which will appear as a title on tooltips.'),
      '#options' => array_merge(['' => ''], $this->viewFields),
      '#default_value' => $this->options['leaflet_country_map']['name_field'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => FALSE],
          ],
        ],
      ],
    ];
    $desc_options = array_merge(['' => ''], $this->viewFields);
    if ($this->entityType) {
      $desc_options += [
        '#rendered_entity' => $this->t('< @entity entity >', ['@entity' => $this->entityType]),
        '#rendered_entity_ajax' => $this->t('< @entity entity via ajax >', ['@entity' => $this->entityType]),
      ];
    }
    $form['leaflet_country_map']['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#description' => $this->t('Choose the field or rendering method which will appear as a description on tooltips or popups.'),
      '#required' => FALSE,
      '#options' => $desc_options,
      '#default_value' => $this->options['leaflet_country_map']['description_field'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => FALSE],
          ],
        ],
      ],
    ];
    $form['leaflet_country_map']['onclick_href'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Country clickable link'),
      '#description' => $this->t('If checked, the country outline will be clickable to navigate the user somewhere else.'),
      '#required' => FALSE,
      '#default_value' => $this->options['leaflet_country_map']['onclick_href'],
    ];
    $form['leaflet_country_map']['onclick_href_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern for link target'),
      '#description' => $this->t('The pattern for the link target. %country will be replaced with the country name.'),
      '#required' => FALSE,
      '#default_value' => $this->options['leaflet_country_map']['onclick_href_pattern'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[onclick_href]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ],
    ];
    $form['leaflet_country_map']['linecolor'] = [
      '#type' => 'textfield',
      '#title' => 'Outline color',
      '#description' => $this->t('Enter a hex value for the outline colour.'),
      '#field_prefix' => '#',
      '#size' => 6,
      '#default_value' => $this->options['leaflet_country_map']['linecolor'],
      '#empty_value' => '666666',

    ];
    $form['leaflet_country_map']['lineweight'] = [
      '#type' => 'textfield',
      '#title' => 'Weight of the outline',
      '#description' => $this->t('Enter a value like 1 or 1.5'),
      '#size' => 6,
      '#default_value' => $this->options['leaflet_country_map']['lineweight'],
      '#empty_value' => '1.5',

    ];
    $form['leaflet_country_map']['lineopacity'] = [
      '#type' => 'textfield',
      '#title' => 'Opacity of the outline',
      '#description' => $this->t('Enter an opacity value from 0 to 1.'),
      '#size' => 6,
      '#default_value' => $this->options['leaflet_country_map']['lineopacity'],
      '#empty_value' => '1',

    ];
    $form['leaflet_country_map']['fillcolor'] = [
      '#type' => 'textfield',
      '#title' => 'Fill color',
      '#description' => $this->t('Enter a hex value for the fill colour of a country'),
      '#field_prefix' => '#',
      '#size' => 6,
      '#default_value' => $this->options['leaflet_country_map']['fillcolor'],
      '#empty_value' => '666666',

    ];
    $form['leaflet_country_map']['fillopacity'] = [
      '#type' => 'textfield',
      '#title' => 'Fill opacity',
      '#description' => $this->t('Enter an opacity value from 0 to 1.'),
      '#size' => 6,
      '#default_value' => $this->options['leaflet_country_map']['fillopacity'],
      '#empty_value' => '1',
    ];

    if ($this->entityType) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => $this->options['view_mode'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[description_field]"]' => [
              ['value' => '#rendered_entity'],
              ['value' => '#rendered_entity_ajax'],
            ],
          ],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    $style_options = $form_state->getValue('style_options');

    $lineopacity = (int) $style_options['leaflet_country_map']['lineopacity'];
    if ($lineopacity < 0 || $lineopacity > 1) {
      $form_state->setError($form['leaflet_country_map']['lineopacity'], $this->t('Please select an opacity value between 0 and 1'));
    }
    $fillopacity = (int) $style_options['leaflet_country_map']['fillopacity'];
    if ($fillopacity < 0 || $fillopacity > 1) {
      $form_state->setError($form['leaflet_country_map']['fillopacity'], $this->t('Please select an opacity value between 0 and 1'));
    }
  }

  /**
   * Get the field type of the current field used as the source of geodata.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The field type.
   */
  protected function getDataSourceFieldType($field_name) {
    $fields_geo_data = [];

    if (is_array($field_name)) {
      $field_name = array_values($field_name)[0];
    }

    // We can assume the data source is a field within the view.
    /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\FieldPluginBase')) {
        /** @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        }
        catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        $type = $field_storage_definition->getType();
        $definition = $this->fieldTypeManager->getDefinition($type);
        if (
          is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\AddressItem', TRUE) ||
          is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\CountryItem', TRUE) ||
          is_a($definition['class'], '\Drupal\leaflet_countries\Plugin\Field\FieldType\LeafletCountryItem', TRUE)
        ) {
          $fields_geo_data[$handler->definition['field_name']]['type'] = $definition['id'];
        }
      }
    }

    return $fields_geo_data[$field_name]['type'];
  }

  /**
   * Renders the View.
   */
  public function render() {

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_view_" . $this->view->id() . '_' . $this->view->current_display);

    // Define the list of geofields set as source of Leaflet View geodata.
    $geofield_name = is_array($this->options['data_source']) ? array_values($this->options['data_source'])[0] : $this->options['data_source'];

    // If the Geofield field is null, output a warning to the  administrator.
    if (empty($geofield_name) && $this->currentUser->hasPermission('configure geofield_map')) {
      $build = [
        '#markup' => '<div class="geofield-map-warning">' . $this->t("The country field has not been correctly set for this view. <br>Add at least one country field to the view and set it as data source in the display settings.") . "</div>",
        '#attached' => [
          'library' => ['leaflet/general'],
        ],
      ];
    }

    if (!empty($geofield_name) && (!empty($this->view->result) || !$this->options['hide_empty_map'])) {
      $this->renderFields($this->view->result);

      // Group the rows according to the grouping instructions, if specified.
      $sets = $this->renderGrouping(
        $this->view->result,
        $this->options['grouping'],
        TRUE
      );
      $data = $this->renderGroupingSets($sets);

      $js_settings = [
        'map' => $map,
        'features' => $data,
      ];

      // Allow other modules to add/alter the map js settings.
      $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);

      $map_height = !empty($this->options['height']) ? $this->options['height'] . $this->options['height_unit'] : '';
      $build = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);

      BubbleableMetadata::createFromRenderArray($build)->applyTo($build);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function renderGroupingSets($sets, $level = 0) {
    $output = [];
    $countries = [];

    // Determine the type of geolocation field.
    $geofield_name = $this->options['data_source'];
    if (is_array($geofield_name)) {
      $geofield_name = reset($geofield_name);
    }
    $geofield_type = $this->getDataSourceFieldType($geofield_name);

    // We want to avoid any grouping configuration, and collect all entities
    // into an array by country, to return an array of countries with their
    // rendered fields or entities as required.
    foreach ($sets as $set) {
      foreach ($set['rows'] as $index => $row) {
        if (!empty($row->_entity)) {
          // Entity API provides a plain entity object.
          $entity = $row->_entity;
        }
        elseif (isset($row->_object)) {
          // Search API provides a TypedData EntityAdapter.
          $entity_adapter = $row->_object;
          if ($entity_adapter instanceof EntityAdapter) {
            $entity = $entity_adapter->getValue();
          }
        }

        $field_values = $entity->get($geofield_name)->getValue();
        foreach ($field_values as $field_value) {
          switch ($geofield_type) {
            case 'leaflet_country_item':
              $cca3 = $field_value['value'];
              break;

            case 'address':
              $country_code = strtoupper($field_value['country_code']);
              $cca3 = Countries::getCca3($country_code);
              break;

            case 'address_country':
              $country_code = strtoupper($field_value['value']);
              $cca3 = Countries::getCca3($country_code);
              break;
          }
          $country_names = Countries::getCodesAndLabels();
          $countries[$cca3]['rows'][] = $row;
          $countries[$cca3]['name'] = $country_names[$cca3];
        }
      }
    }

    // We should now have an array of countries, indexed by cca3 code, each with
    // one or more rows.
    foreach ($countries as $cca3 => $country) {
      // Render outlines, one per country, passing just the first row to render
      // the outline.
      $country_data = $this->renderCountryRow($country['rows'][0], $cca3);
      $newoutput['rows'][$cca3] = $country_data;
      // If a description field is set, add a popup.
      if ($this->options['leaflet_country_map']['description_field']) {
        // Render popup content, one per row in each country.
        $newoutput['rows'][$cca3]['popup'] = $this->renderPopupRows($country);
      }

      if ($this->options['leaflet_country_map']['country_name_tooltip']) {
        // If we have a label, add it.
        // $newoutput['rows'][$cca3]['label'] = $country['name'];.
        $newoutput['rows'][$cca3]['tooltip'] = $country['name'];
        // $newoutput['rows'][$cca3]['popup'] = $country['name'];
      }
    }

    foreach ($sets as $set) {

      $set['features'] = $newoutput['rows'];
      // Not sure exactly what a feature group is.
      if ($feature_group = $this->renderLeafletGroup($set['features'], $set['group'], $level)) {
        // Allow modules to adjust the feature group.
        $this->moduleHandler->alter('leaflet_views_feature_group', $feature_group, $this);

        // If the rendered "feature group" is actually only a list of features,
        // merge them into the output; else simply append the feature group.
        if (empty($feature_group['group'])) {
          $output = array_merge($output, $feature_group['features']);
        }
        else {
          $output[] = $feature_group;
        }
      }

    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function renderCountryRow($row, $cca3) {

    // Load the GeoJSON data.
    $data = json_decode(Countries::getIndividualCountryJSON($cca3), TRUE);
    $geojson = [
      'type' => 'topojson',
      'json' => $data,
    ];

    // Prepare the leaflet feature country outline.
    $outline = $this->renderLeafletOutline($geojson, $row, $cca3);

    return $outline;
  }

  /**
   * Converts the given geo data points into a leaflet feature.
   *
   * @param array $feature
   *   A list of points.
   * @param \Drupal\views\ResultRow $row
   *   The views result row.
   * @param string $code
   *   The country code.
   *
   * @return array
   *   Leaflet features.
   */
  protected function renderLeafletOutline($feature, ResultRow $row, $code) {

    // Render the entity with the selected view mode.
    $popup_body = $this->renderRowContent($row);
    $label = $this->view->getStyle()->getField($row->index, $this->options['leaflet_country_map']['name_field']);

    $feature['popup'] = $popup_body;
    $feature['label'] = $label;
    $feature['labelTriggerPopup'] = $this->options['leaflet_country_map']['name_trigger_popup'];

    // Get country name and format href target.
    if ($this->options['leaflet_country_map']['onclick_href']) {
      $country_names = Countries::getCodesAndLabels();
      $country_name = $country_names[$code];
      $pattern = $this->options['leaflet_country_map']['onclick_href_pattern'];
      $feature['href'] = str_replace('%country', $country_name, $pattern);
    }

    // Here we need to get the cca3 code.
    $feature['code'] = $code;
    $feature['options'] = [
      'color' => '#' . $this->options['leaflet_country_map']['linecolor'] ?? '666666',
      'weight' => $this->options['leaflet_country_map']['lineweight'] ?? '1.5',
      'lineOpacity' => $this->options['leaflet_country_map']['lineopacity'] ?? '1',
      'fillColor' => '#' . $this->options['leaflet_country_map']['fillcolor'] ?? '666666',
      'fillOpacity' => $this->options['leaflet_country_map']['fillopacity'] ?? '1',
    ];

    return $feature;
  }

  /**
   * Renders the row content for leaflet popup.
   *
   * @param \Drupal\views\ResultRow $row
   *   The views result row.
   *
   * @return string
   *   Markup to populate the popup.
   */
  protected function renderRowContent(ResultRow $row) {
    $popup_body = '';
    if ($this->options['leaflet_country_map']['description_field'] === '#rendered_entity' && is_object($row->_entity)) {
      $entity = $row->_entity;
      $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $this->options['view_mode'], $entity->language());
      $popup_body = $this->renderer->render($build);
    }
    // Normal rendering via fields.
    elseif ($this->options['leaflet_country_map']['description_field']) {
      $popup_body = $this->view->getStyle()
        ->getField($row->index, $this->options['leaflet_country_map']['description_field']);
    }
    return $popup_body;
  }

  /**
   * Renders the popup content for a number of rows.
   *
   * @param array $rows
   *   Array of views rows.
   *
   * @return string
   *   Rendered markup.
   */
  protected function renderPopupRows($rows) {
    $markup = '';
    foreach ($rows['rows'] as $row) {
      $markup .= $this->renderRowContent($row);
    }
    $renderable = [
      '#theme' => 'leaflet_countries_popup',
      '#items' => ['#markup' => $markup],
      '#rows' => $rows['rows'],
      '#country_name' => $rows['name'],
    ];
    $rendered = \Drupal::service('renderer')->render($renderable);
    return $rendered;
  }

  /**
   * Render a single group of leaflet features.
   *
   * @param array $features
   *   The list of leaflet features / points.
   * @param string $title
   *   The group title.
   * @param string $level
   *   The current group level.
   *
   * @return array
   *   Definition of leaflet features, compatible with leaflet_render_map().
   */
  protected function renderLeafletGroup($features, $title, $level) {
    return [
      'group' => FALSE,
      'features' => $features ?? [],
    ];
  }

}
