<?php

namespace Drupal\leaflet_mapbox\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the Mapbox maps exposed to Leaflet.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Prefix used for the keys of maps that have not been saved yet.
   */
  const NEW_KEY_PREFIX = '_new_';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'leaflet_mapbox_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['leaflet_mapbox.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $maps = $form_state->get('maps');
    if ($maps === NULL) {
      $maps = $this->config('leaflet_mapbox.settings')->get('maps') ?? [];
      $form_state->set('maps', $maps);
    }

    $form['#tree'] = TRUE;
    $form['maps'] = [
      '#type' => 'container',
      '#prefix' => '<div id="leaflet-mapbox-maps-wrapper">',
      '#suffix' => '</div>',
    ];

    if (!$maps) {
      $form['maps']['empty'] = [
        '#type' => 'item',
        '#markup' => $this->t('No maps are configured yet. Add one below.'),
      ];
    }

    foreach ($maps as $key => $map) {
      $form['maps'][$key] = $this->buildMapElement($key, $map);
    }

    $form['add_map'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another map'),
      '#submit' => ['::addMap'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'leaflet-mapbox-maps-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the form elements for a single map.
   *
   * @param string $key
   *   The map key in configuration.
   * @param array $map
   *   The stored map values.
   *
   * @return array
   *   The form element.
   */
  protected function buildMapElement($key, array $map) {
    $is_new = str_starts_with($key, static::NEW_KEY_PREFIX);
    $map_id = $key === 'default' ? 'leaflet-mapbox' : 'leaflet-mapbox-' . $key;

    $element = [
      '#type' => 'details',
      '#title' => $is_new
        ? $this->t('New map')
        : $this->t('@label (@id)', ['@label' => $map['label'] ?: $key, '@id' => $map_id]),
      '#open' => TRUE,
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map label'),
      '#required' => TRUE,
      '#default_value' => $map['label'] ?? '',
      '#description' => $this->t('Give your map a name, this will be the name shown on the display options form.'),
    ];

    $element['api_version'] = [
      '#type' => 'select',
      '#title' => $this->t('API version'),
      '#required' => TRUE,
      '#default_value' => $map['api_version'] ?? '4',
      '#options' => [
        '3' => $this->t('3 (Mapbox Studio Classic)'),
        '4' => $this->t('4 (Mapbox Studio)'),
      ],
    ];

    $element['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map code'),
      '#default_value' => $map['code'] ?? '',
      '#description' => $this->t('This code is obtained from Mapbox by clicking on the mapbox.js button after publishing your map'),
      '#states' => [
        'visible' => [
          ':input[name="maps[' . $key . '][api_version]"]' => ['value' => '3'],
        ],
        'required' => [
          ':input[name="maps[' . $key . '][api_version]"]' => ['value' => '3'],
        ],
      ],
    ];

    $element['style_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Style URL'),
      '#default_value' => $map['style_url'] ?? '',
      '#description' => $this->t('Copy and paste the style URL. Example: %url.', [
        '%url' => 'mapbox://styles/johndoe/erl4zrwto008ob3f2ijepsbszg',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="maps[' . $key . '][api_version]"]' => ['value' => '4'],
        ],
        'required' => [
          ':input[name="maps[' . $key . '][api_version]"]' => ['value' => '4'],
        ],
      ],
    ];

    $element['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map access token'),
      '#required' => TRUE,
      '#default_value' => $map['token'] ?? '',
      '#description' => $this->t('You will find this in the Mapbox user account settings'),
    ];

    $element['zoomlevel'] = [
      '#type' => 'number',
      '#title' => $this->t('Zoom Level'),
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 19,
      '#default_value' => $map['zoomlevel'] ?? 2,
      '#description' => $this->t('You must clear the site caches after changing this value or wait for the caches to expire before this change shows'),
    ];

    $element['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map description'),
      '#default_value' => $map['description'] ?? '',
    ];

    $element['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove this map'),
      '#name' => 'remove_map_' . $key,
      '#map_key' => $key,
      '#submit' => ['::removeMap'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['button--danger'],
      ],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'leaflet-mapbox-maps-wrapper',
      ],
    ];

    return $element;
  }

  /**
   * Submit handler for the "Add another map" button.
   */
  public function addMap(array &$form, FormStateInterface $form_state) {
    $maps = $form_state->get('maps');
    $count = ($form_state->get('new_map_count') ?? 0) + 1;
    $maps[static::NEW_KEY_PREFIX . $count] = [
      'label' => '',
      'api_version' => '4',
      'code' => '',
      'style_url' => '',
      'token' => '',
      'zoomlevel' => 2,
      'description' => '',
    ];
    $form_state->set('maps', $maps);
    $form_state->set('new_map_count', $count);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "Remove this map" buttons.
   *
   * Only removes the map from the form; nothing is deleted from
   * configuration until the form is saved.
   */
  public function removeMap(array &$form, FormStateInterface $form_state) {
    $key = $form_state->getTriggeringElement()['#map_key'];
    $maps = $form_state->get('maps');
    unset($maps[$key]);
    $form_state->set('maps', $maps);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the add and remove buttons.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    return $form['maps'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $maps = [];
    foreach ($form_state->getValue('maps', []) as $key => $values) {
      if (!is_array($values) || !isset($values['label'])) {
        continue;
      }
      if (str_starts_with($key, static::NEW_KEY_PREFIX)) {
        $key = $this->generateMapKey($values['label'], $maps);
      }
      $maps[$key] = [
        'label' => $values['label'],
        'api_version' => $values['api_version'],
        'code' => $values['code'],
        'style_url' => $values['style_url'],
        'token' => $values['token'],
        'zoomlevel' => (int) $values['zoomlevel'],
        'description' => $values['description'],
      ];
    }

    $this->config('leaflet_mapbox.settings')
      ->setData(['maps' => $maps])
      ->save();

    $form_state->set('maps', NULL);
  }

  /**
   * Generates a unique machine key for a new map based on its label.
   *
   * @param string $label
   *   The map label.
   * @param array $existing
   *   The maps already keyed, to avoid collisions.
   *
   * @return string
   *   A unique machine key.
   */
  protected function generateMapKey($label, array $existing) {
    $key = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($label)));
    $key = trim($key, '_') ?: 'map';
    $base = $key;
    $suffix = 1;
    while (isset($existing[$key])) {
      $key = $base . '_' . $suffix++;
    }
    return $key;
  }

}
