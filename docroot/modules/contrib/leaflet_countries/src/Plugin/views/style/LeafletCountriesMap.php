<?php

namespace Drupal\leaflet_countries\Plugin\views\style;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\leaflet_views\Controller\LeafletAjaxPopupController;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Leaflet\LeafletService;
use Drupal\Component\Utility\Html;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Views;
use Drupal\Core\Entity\EntityTypeInterface;
use \Drupal\leaflet_countries\Plugin\Field\FieldType\LeafletCountryItem;
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
class LeafletCountriesMap extends StylePluginBase implements ContainerFactoryPluginInterface
{

  use LeafletSettingsElementsTrait;

  /**
   * The Default Settings.
   *
   * @var array
   */
  protected $defaultSettings;

  /**
   * The Entity source property.
   *
   * @var string
   */
  protected $entitySource;

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * The Entity type property.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Entity Info service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityInfo;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity Field manager service property.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Display Repository service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;


  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The list of fields added to the view.
   *
   * @var array
   */
  protected $viewFields = [];

  /**
   * Field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Constructs a LeafletMap style instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LeafletService $leaflet_service,
    LinkGeneratorInterface $link_generator,
    FieldTypePluginManagerInterface $field_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->defaultSettings = self::getDefaultSettings();
    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->leafletService = $leaflet_service;
    $this->link = $link_generator;
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('leaflet.service'),
      $container->get('link_generator'),
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL)
  {
    parent::init($view, $display, $options);

    // We want to allow view editors to select which entity out of a
    // possible set they want to use to pass to the MapThemer plugin. Long term
    // it would probably be better not to pass an entity to MapThemer plugin and
    // instead pass the result row.
    if (!empty($options['entity_source']) && $options['entity_source'] != '__base_table') {
      $handler = $this->displayHandler->getHandler('relationship', $options['entity_source']);
      $this->entitySource = $options['entity_source'];

      $data = Views::viewsData();
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          $this->entityInfo = $this->entityManager->getDefinition($table['table']['entity type']);
          $this->entityType = $this->entityInfo->id();
        } catch (\Exception $e) {
          watchdog_exception('geofield_map', $e);
        }
      }
    } else {
      $this->entitySource = '__base_table';

      // For later use, set entity info related to the View's base table.
      $base_tables = array_keys($view->getBaseTables());
      $base_table = reset($base_tables);
      if ($this->entityInfo = $view->getBaseEntityType()) {
        $this->entityType = $this->entityInfo->id();
        return;
      }

      // Eventually try to set entity type & info from base table suffix
      // (i.e. Search API views).
      if (!isset($this->entityType)) {
        $index_id = substr($base_table, 17);
        $index = Index::load($index_id);
        foreach ($index->getDatasources() as $datasource) {
          if ($datasource instanceof DatasourceInterface) {
            $this->entityType = $datasource->getEntityTypeId();
            try {
              $this->entityInfo = $this->entityManager->getDefinition($this->entityType);
            } catch (\Exception $e) {
              watchdog_exception('leaflet', $e);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue($index, $field)
  {
    $this->view->row_index = $index;
    $value = isset($this->view->field[$field]) ? $this->view->field[$field]->getValue($this->view->result[$index]) : NULL;
    unset($this->view->row_index);
    return $value;
  }

  /**
   * Get a list of fields and a sublist of geo data fields in this view.
   *
   * @return array
   *   Available data sources.
   */
  protected function getAvailableDataSources()
  {
    $fields_geo_data = [];

    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        } catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        $type = $field_storage_definition->getType();
        $definition = $this->fieldTypeManager->getDefinition($type);
        if (is_a($definition['class'], '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem', TRUE)) {
          $fields_geo_data[$field_id] = $label;
        }
        // Support Address field. Drupal\address\Plugin\Field\FieldType
        if (is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\AddressItem', TRUE)) {
          $fields_geo_data[$field_id] = $label;
        }
        // Support Leaflet Country field.
        if (is_a($definition['class'], '\Drupal\leaflet_countries\Plugin\Field\FieldType\LeafletCountryItem', TRUE)) {
          $fields_geo_data[$field_id] = $label;
        }
      }
    }

    return $fields_geo_data;
  }

  /**
   * Get options for the available entity sources.
   *
   * Entity source controls which entity gets passed to the MapThemer plugin. If
   * not set it will always default to the view base entity.
   *
   * @return array
   *   The entity sources list.
   */
  protected function getAvailableEntitySources()
  {
    if ($base_entity_type = $this->view->getBaseEntityType()) {
      $label = $base_entity_type->getLabel();
    } else {
      // Fallback to the base table key.
      $base_tables = array_keys($this->view->getBaseTables());
      // A view without a base table should never happen (just in case).
      $label = $base_tables[0] ?? $this->t('Unknown');
    }

    $options = [
      '__base_table' => new TranslatableMarkup('View Base Entity (@entity_type)', [
        '@entity_type' => $label,
      ]),
    ];

    $data = Views::viewsData();
    /** @var \Drupal\views\Plugin\views\HandlerBase $handler */
    foreach ($this->displayHandler->getHandlers('relationship') as $relationship_id => $handler) {
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          $entity_type = $this->entityManager->getDefinition($table['table']['entity type']);
        } catch (\Exception $e) {
          $entity_type = NULL;
        }
        $options[$relationship_id] = new TranslatableMarkup('@relationship (@entity_type)', [
          '@relationship' => $handler->adminLabel(),
          '@entity_type' => $entity_type->getLabel(),
        ]);
      }
    }

    return $options;
  }

  /**
   * Get the entity info of the entity source.
   *
   * @param string $source
   *   The Source identifier.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  protected function getEntitySourceEntityInfo($source)
  {
    if (!empty($source) && ($source != '__base_table')) {
      $handler = $this->displayHandler->getHandler('relationship', $source);

      $data = Views::viewsData();
      if (($table = $data->get($handler->definition['base'])) && !empty($table['table']['entity type'])) {
        try {
          return $this->entityManager->getDefinition($table['table']['entity type']);
        } catch (\Exception $e) {
          $entity_type = NULL;
        }
      }
    }

    return $this->view->getBaseEntityType();
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty()
  {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state)
  {

    // If data source changed then apply the changes.
    if ($form_state->get('entity_source')) {
      $this->options['entity_source'] = $form_state->get('entity_source');
      $this->entityInfo = $this->getEntitySourceEntityInfo($this->options['entity_source']);
      $this->entityType = $this->entityInfo->id();
      $this->entitySource = $this->options['entity_source'];
    }

    parent::buildOptionsForm($form, $form_state);

    $form['#tree'] = TRUE;
    $form['#attached'] = [
      'library' => [
        'leaflet/general',
      ],
    ];

    // Get a sublist of geo data fields in the view.
    $fields_geo_data = $this->getAvailableDataSources();

    // Check whether we have a geo data field we can work with.
    if (!count($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one Geofield to the View and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['leaflet-warning'],
        ],
      ];
      return;
    }

    $wrapper_id = 'leaflet-map-views-style-options-form-wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // Map preset.
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which field contains geodata?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
      '#weight' => 0,
    ];

    // Get the possible entity sources.
    $entity_sources = $this->getAvailableEntitySources();

    // If there is only one entity source it will be the base entity, so don't
    // show the element to avoid confusing people.
    if (count($entity_sources) == 1) {
      $form['entity_source'] = [
        '#type' => 'value',
        '#value' => key($entity_sources),
      ];
    } else {
      $form['entity_source'] = [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('Entity Source'),
        '#description' => new TranslatableMarkup('Select which Entity should be used as Leaflet Mapping base Entity.<br><u>Leave as "View Base Entity" to rely on default Views behaviour, and don\'t specifically needed otherwise</u>.'),
        '#options' => $entity_sources,
        '#default_value' => !empty($this->options['entity_source']) ? $this->options['entity_source'] : '__base_table',
        '#ajax' => [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'optionsFormEntitySourceSubmitAjax'],
          'trigger_as' => ['name' => 'entity_source_submit'],
        ],
      ];
      $form['entity_source_submit'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Update Entity Source'),
        '#name' => 'entity_source_submit',
        '#submit' => [
          [static::class, 'optionsFormEntitySourceSubmit'],
        ],
        '#validate' => [],
        '#limit_validation_errors' => [
          ['style_options', 'entity_source'],
        ],
        '#attributes' => [
          'class' => ['js-hide'],
        ],
        '#ajax' => [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'optionsFormEntitySourceSubmitAjax'],
        ],
      ];
    }

    // Group by country
    $form['group_by_country'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group by country'),
      '#description' => $this->t('Group all entities by country, for example to allow a single popup per country, listing the entities within that country.'),
      '#default_value' => $this->options['group_by_country'],
    ];

    // Country name as tool tip
    $form['country_name_tooltip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display country name as tool tip.'),
      '#description' => $this->t('Show the country name as the tool tip.'),
      '#default_value' => $this->options['country_name_tooltip'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ]
    ];

    // Name field.
    $form['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field which will appear as a title on tooltips.'),
      '#options' => array_merge(['' => ''], $this->viewFields),
      '#default_value' => $this->options['name_field'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => FALSE],
          ],
        ],
      ],
    ];

    $desc_options = array_merge(['' => ''], $this->viewFields);
    // Add an option to render the entire entity using a view mode.
    if ($this->entityType) {
      $desc_options += [
        '#rendered_entity' => $this->t('< @entity entity >', ['@entity' => $this->entityType]),
        '#rendered_entity_ajax' => $this->t('< @entity entity via ajax >', ['@entity' => $this->entityType]),
      ];
    }

    $form['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#description' => $this->t('Choose the field or rendering method which will appear as a description on tooltips or popups.'),
      '#required' => FALSE,
      '#options' => $desc_options,
      '#default_value' => $this->options['description_field'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[group_by_country]"]' => [
            ['checked' => FALSE],
          ],
        ],
      ],
    ];

    // Should the country polygon be clickable to navigate the user somewhere?
    $form['onclick_href'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Country clickable link'),
      '#description' => $this->t('If checked, the country outline will be clickable to navigate the user somewhere else.'),
      '#required' => FALSE,
      '#default_value' => $this->options['onclick_href'],
    ];
    // If the country outline is clickable, set a default href pattern for the link.
    $form['onclick_href_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern for link target'),
      '#description' => $this->t('The pattern for the link target. %country will be replaced with the country name.'),
      '#required' => FALSE,
      '#default_value' => $this->options['onclick_href_pattern'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[onclick_href]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ],
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

    // The outline of a country.
    $form['linecolor'] = array(
      '#type' => 'textfield',
      '#title' => 'Outline color',
      '#description' => $this->t('Enter a hex value for the outline colour.'),
      '#field_prefix' => '#',
      '#size' => 6,
      '#default_value' => $this->options['linecolor'],
      '#empty_value' => '666666',

    );

    // The line weight of the line surrounding the country.
    $form['lineweight'] = array(
      '#type' => 'textfield',
      '#title' => 'Weight of the outline',
      '#description' => $this->t('Enter a value like 1 or 1.5'),
      '#size' => 6,
      '#default_value' => $this->options['lineweight'],
      '#empty_value' => '1.5',

    );

    // The opacity of the line surrounding the country.
    $form['lineopacity'] = array(
      '#type' => 'textfield',
      '#title' => 'Opacity of the outline',
      '#description' => $this->t('Enter an opacity value from 0 to 1.'),
      '#size' => 6,
      '#default_value' => $this->options['lineopacity'],
      '#empty_value' => '1',

    );

    // The hex value for the fill colour.
    $form['fillcolor'] = array(
      '#type' => 'textfield',
      '#title' => 'Fill color',
      '#description' => $this->t('Enter a hex value for the fill colour of a country'),
      '#field_prefix' => '#',
      '#size' => 6,
      '#default_value' => $this->options['fillcolor'],
      '#empty_value' => '666666',

    );

    // The opacity value for the fill.
    $form['fillopacity'] = array(
      '#type' => 'textfield',
      '#title' => 'Fill opacity',
      '#description' => $this->t('Enter an opacity value from 0 to 1.'),
      '#size' => 6,
      '#default_value' => $this->options['fillopacity'],
      '#empty_value' => '1',
    );

    // setMaxBounds
    $form['setmaxbounds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the Leaflet setMaxBounds option'),
      '#description' => $this->t('This restricts dragging outside of the bounds set by the country outlines.'),
      '#default_value' => $this->options['setmaxbounds'],
    ];

    // maxBoundsViscosity.
    $form['maxboundsviscosity'] = array(
      '#type' => 'textfield',
      '#title' => 'Set maxBoundsViscosity',
      '#description' => $this->t('If maxBounds is set, this option will control how solid the bounds are when dragging the map around. The default value of 0.0 allows the user to drag outside the bounds at normal speed, higher values will slow down map dragging outside bounds, and 1.0 makes the bounds fully solid, preventing the user from dragging outside the bounds.'),
      '#size' => 6,
      '#default_value' => $this->options['maxboundsviscosity'],
      '#empty_value' => '0.0',
    );

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($form, $this->options);

    // Generate the Leaflet Map Reset Control.
    $this->setResetMapControl($form, $this->options);

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $this->options['map_position'];
    $form['map_position'] = $this->generateMapPositionElement($map_position_options);

    // Generate Icon form element.
    $icon_options = $this->options['icon'];
    $form['icon'] = $this->generateIconFormElement($icon_options);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($form, $this->options);

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $this->options);

    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    $this->setGeocoderMapControl($form, $this->options);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state)
  {
    parent::validateOptionsForm($form, $form_state);

    $style_options = $form_state->getValue('style_options');
    if (!empty($style_options['height']) && (!is_numeric($style_options['height']) || $style_options['height'] <= 0)) {
      $form_state->setError($form['height'], $this->t('Map height needs to be a positive number.'));
    }
    $icon_options = isset($style_options['icon']) ? $style_options['icon'] : [];
    if (!empty($icon_options['iconSize']['x']) && (!is_numeric($icon_options['iconSize']['x']) || $icon_options['iconSize']['x'] <= 0)) {
      $form_state->setError($form['icon']['iconSize']['x'], $this->t('Icon width needs to be a positive number.'));
    }
    if (!empty($icon_options['iconSize']['y']) && (!is_numeric($icon_options['iconSize']['y']) || $icon_options['iconSize']['y'] <= 0)) {
      $form_state->setError($form['icon']['iconSize']['y'], $this->t('Icon height needs to be a positive number.'));
    }

    $lineopacity = $form_state->getValue(array('lineopacity'));
    $fillopacity = $form_state->getValue(array('fillopacity'));

    if ($lineopacity < 0 || $lineopacity > 1) {
      $form_state->setError($form['lineopacity'], $this->t('Please select an opacity value between 0 and 1'));
    }

    if ($fillopacity < 0 || $fillopacity > 1) {
      $form_state->setError($form['fillopacity'], $this->t('Please select an opacity value between 0 and 1'));
    }
  }

  /**
   * Submit to update the data source.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   */
  public static function optionsFormEntitySourceSubmit(array $form, FormStateInterface $form_state)
  {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    array_push($parents, 'entity_source');

    // Set the data source selected in the form state and rebuild the form.
    $form_state->set('entity_source', $form_state->getValue($parents));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback to reload the options form after data source change.
   *
   * This allows the entityType (which can be affected by which source
   * is selected to alter the form.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return mixed
   *   The returned result.
   */
  public static function optionsFormEntitySourceSubmitAjax(array $form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);

    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Get the field type of the current field used as the source of geodata.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string $field_type
   *   The field type.
   */
  protected function getDataSourceFieldType($field_name)
  {
    $fields_geo_data = [];
    // We can assume the data source is a field within the view.
    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        } catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        $type = $field_storage_definition->getType();
        $definition = $this->fieldTypeManager->getDefinition($type);
        // Support for geofield.
        if (is_a($definition['class'], '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem', TRUE)) {
          // @todo: add support for geofield.
          //$fields_geo_data[$field_id]['type'] = $definition['id'];
        }
        // Support Address field. Drupal\address\Plugin\Field\FieldType
        if (is_a($definition['class'], '\Drupal\address\Plugin\Field\FieldType\AddressItem', TRUE)) {
          $fields_geo_data[$field_id]['type'] = $definition['id'];
        }
        // Support Leaflet Country field.
        if (is_a($definition['class'], '\Drupal\leaflet_countries\Plugin\Field\FieldType\LeafletCountryItem', TRUE)) {
          $fields_geo_data[$field_id]['type'] = $definition['id'];
        }
      }
    }

    return $fields_geo_data[$field_name]['type'];
  }

  /**
   * Renders the View.
   */
  public function render()
  {
    // Performs some preprocess on the leaflet map settings.
    // Function removed from Leaflet – commented out next line cb 2020-11-27
    // $this->leafletService->preProcessMapSettings($this->options);

    $data = [];

    // Collect bubbleable metadata when doing early rendering.
    $build_for_bubbleable_metadata = [];

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_view_" . $this->view->id() . '_' . $this->view->current_display);

    // Determine the type of geolocation field.
    $geofield_name = $this->options['data_source'];
    $geofield_type = $this->getDataSourceFieldType($geofield_name);

    // For a geofield data source.
    if ($geofield_type == 'geofield') {
      // @todo: add support for geofield.
    }

    // For a leaflet_countries field data source.
    if ($geofield_type == 'leaflet_country_item') {

      // Group the rows according to the grouping instructions, if specified.
      $sets = $this->renderGrouping(
        $this->view->result,
        $this->options['grouping'],
        TRUE
      );

      $data = $this->renderGroupingSets($sets);
    }

    // For address field data source.
    if ($geofield_type == 'address') {

      // Group the rows according to the grouping instructions, if specified.
      $sets = $this->renderGrouping(
        $this->view->result,
        $this->options['grouping'],
        TRUE
      );

      $data = $this->renderGroupingSets($sets);
    }
    // Don't render the map, if we do not have any data
    // and the hide option is set.
    if (empty($data) && !empty($this->options['hide_empty_map'])) {
      return [];
    }

    // Set maxBoundsViscosity, from views style settings.

    $map['settings']['setMaxBounds']  = $this->options['setmaxbounds'];
    $map['settings']['maxBoundsViscosity']  = $this->options['maxboundsviscosity'];
    // Reset the map path to allow overrides from views style config.
    $map['settings']['path']  = '';


    $js_settings = [
      'map' => $map,
      'features' => $data,
    ];

    // Allow other modules to add/alter the map js settings.
    $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);

    $map_height = !empty($this->options['height']) ? $this->options['height'] . $this->options['height_unit'] : '';
    $build = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);
    BubbleableMetadata::createFromRenderArray($build)
      ->merge(BubbleableMetadata::createFromRenderArray($build_for_bubbleable_metadata))
      ->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function renderGroupingSets($sets, $level = 0)
  {
    $output = array();
    $countries = array();

    // Determine the type of geolocation field.
    $geofield_name = $this->options['data_source'];
    $geofield_type = $this->getDataSourceFieldType($geofield_name);
    // We want to avoid any grouping configuration, and collect all entities into an array by country,
    // to return an array of coutries with their rendered fields or entities as required.
    foreach ($sets as $set) {
      foreach ($set['rows'] as $index => $row) {

        if (!empty($row->_entity)) {
          // Entity API provides a plain entity object.
          $entity = $row->_entity;
        } elseif (isset($row->_object)) {
          // Search API provides a TypedData EntityAdapter.
          $entity_adapter = $row->_object;
          if ($entity_adapter instanceof EntityAdapter) {
            $entity = $entity_adapter->getValue();
          }
        }

        $field_values = $entity->get($this->options['data_source'])->getValue();


        foreach ($field_values as $field_value) {
          switch ($geofield_type) {
            case 'leaflet_country_item':
              $cca3  = $field_value['value'];
              break;
            case 'address':
              $country_code = strtoupper($field_value['country_code']);
              $cca3 = \Drupal\leaflet_countries\Countries::getCca3($country_code);
              break;
          }
          $country_names = \Drupal\leaflet_countries\Countries::getCodesAndLabels();
          $countries[$cca3]['rows'][] = $row;
          $countries[$cca3]['name'] = $country_names[$cca3];
        }
      }
    }

    // We should now have an array of countries, indexed by cca3 code, each with one or more rows.
    foreach ($countries as $cca3 => $country) {
      // Render outlines, one per country, passing just the first row to render the outline.
      $country_data = $this->renderCountryRow($country['rows'][0], $cca3);
      $newoutput['rows'][$cca3] = $country_data;
      // If a description field is set, add a popup.
      if ($this->options['description_field']) {
        // render popup content, one per row in each country
        $newoutput['rows'][$cca3]['popup'] = $this->renderPopupRows($country);
      }

      if ($this->options['country_name_tooltip']) {
        // If we have a label, add it.

        //$newoutput['rows'][$cca3]['label'] = $country['name'];
        $newoutput['rows'][$cca3]['tooltip'] = $country['name'];
        //$newoutput['rows'][$cca3]['popup'] = $country['name'];
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
        } else {
          $output[] = $feature_group;
        }
      }
    }

    // I think this is called to leave the view object without a pointer to the current index. @todo check what this is for.
    unset($this->view->row_index);

    return $output;
  }


  /**
   * {@inheritdoc}
   */
  public function renderCountryRow($row, $cca3)
  {

    // Load the GeoJSON data.
    $data = json_decode(\Drupal\leaflet_countries\Countries::getIndividualCountryJSON($cca3), TRUE);
    // Process the GeoJSON data.
    $geojson = array(
      'type' => 'topojson',
      'json' => $data,
    );
    // Prepare the leaflet feature country outline.
    $outline = $this->renderLeafletOutline($geojson, $row, $cca3);

    // Return a single country outline.
    return $outline;
  }


  /**
   * Converts the given list of geo data points into a list of leaflet features.
   *
   * @param array $features
   *   A list of points.
   * @param ResultRow $row
   *   The views result row.
   *
   * @return array
   *   List of leaflet features.
   */
  protected function renderLeafletOutlines($features, ResultRow $row, $code)
  {
    foreach ($features as &$feature) {
      $features[] = $this->renderLeafletOutline($feature, $row, $code);
    }
    return $features;
  }
  /**
   * Converts the given geo data points into a leaflet feature.
   *
   * @param array $feature
   *   A list of points.
   * @param ResultRow $row
   *   The views result row.
   *
   * @return array
   *   Leaflet features.
   */
  protected function renderLeafletOutline($feature, ResultRow $row, $code)
  {

    // Render the entity with the selected view mode.
    $popup_body = $this->renderRowContent($row);

    $label = $this->view->getStyle()
      ->getField($row->index, $this->options['name_field']);

    $feature['popup'] = $popup_body; //$popup_body;
    $feature['label'] = $label;
    $feature['labelTriggerPopup'] = $this->options['name_trigger_popup'];

    // Determine the type of geolocation field.
    $geofield_name = $this->options['data_source'];
    $geofield_type = $this->getDataSourceFieldType($geofield_name);


    // Get country name and format href target.
    if ($this->options['onclick_href']) {
      if ($geofield_type == 'address') {
        // Get country names in the same format as addressfield (eg. Congo - Brazzaville).
        $list = \Drupal\Core\Locale\CountryManager::getStandardList();
        $countries = \Drupal\leaflet_countries\Countries::getCountriesCca3();
        $cca2 = $countries[$code]->cca2;
        $country_name = $list[$cca2]->__toString();
      } else {
        $country_names = \Drupal\leaflet_countries\Countries::getCodesAndLabels();
        $country_name = $country_names[$code];
      }
      $pattern = $this->options['onclick_href_pattern'];
      $feature['href'] = str_replace('%country', $country_name, $pattern);
    }
    // Here we need to get the cca3 code.
    $feature['code'] = $code;

    $feature['options'] = array(
      'color' => isset($this->options['linecolor']) ? '#' . $this->options['linecolor'] : '#666666',
      'weight' => isset($this->options['lineweight']) ? $this->options['lineweight'] : '1.5',
      'lineOpacity' => isset($this->options['lineopacity']) ? $this->options['lineopacity'] : '1',
      'fillColor' => isset($this->options['fillcolor']) ? '#' . $this->options['fillcolor'] : '#666666',
      'fillOpacity' => isset($this->options['fillopacity']) ? $this->options['fillopacity'] : '1',
    );

    return $feature;
  }

  /**
   * Renders the row contnt for leaflet popup.
   *
   * @param array $features
   *   A list of points.
   * @param ResultRow $row
   *   The views result row.
   *
   * @return string
   *   Markup to pupulate the popup.
   */
  protected function renderRowContent(ResultRow $row)
  {
    $popup_body = '';
    if ($this->options['description_field'] === '#rendered_entity' && is_object($row->_entity)) {
      $entity = $row->_entity;
      $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $this->options['view_mode'], $entity->language());
      $popup_body = $this->renderer->render($build);
      //$popup_body = $build;
    }
    // Normal rendering via fields.
    elseif ($this->options['description_field']) {
      $popup_body = $this->view->getStyle()
        ->getField($row->index, $this->options['description_field']);
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
  protected function renderPopupRows($country)
  {
    // @todo: refactor this to make better use of the theme system to render the popup content.
    $markup = '';
    foreach ($country['rows'] as $row) {
      $markup .= $this->renderRowContent($row);
    }
    $renderable = [
      '#theme' => 'leaflet_countries_popup',
      '#items' => ['#markup' => $markup],
      '#rows' => $country['rows'],
      '#country_name' => $country['name'],
    ];
    $rendered = \Drupal::service('renderer')->render($renderable);
    return $rendered;
  }

  /**
   * Set default options.
   */
  protected function defineOptions()
  {
    $options = parent::defineOptions();
    $options['data_source'] = ['default' => ''];
    $options['entity_source'] = ['default' => '__base_table'];
    $options['name_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['view_mode'] = array('default' => 'teaser');
    $options['linecolor'] = array('default' => '666666');
    $options['lineweight'] = array('default' => '1.5');
    $options['lineopacity'] = array('default' => '1');
    $options['fillopacity'] = array('default' => '1');
    $options['fillcolor'] = array('default' => '666666');
    $options['name_trigger_popup'] = TRUE;
    $options['onclick_href'] = FALSE;
    $options['onclick_href_pattern'] = array('default' => '/explore');
    $options['maxboundsviscosity'] = array('default' => '0.0');
    $options['setmaxbounds'] = FALSE;

    $leaflet_map_default_settings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $leaflet_map_default_settings[$k] = ['default' => $setting];
    }
    return $options + $leaflet_map_default_settings;
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
  protected function renderLeafletGroup(array $features = array(), $title, $level)
  {
    return array(
      'group' => FALSE,
      'features' => $features,
    );
  }

  /**
   * Chance for sub-classes to adjust the leaflet feature array.
   *
   * For example, this can be used to add in icon configuration.
   *
   * @param array $feature
   *   The country outline feature.
   * @param ResultRow $row
   *   The Result rows.
   */
  protected function alterLeafletFeature(array &$point, ResultRow $row)
  {
  }
}
