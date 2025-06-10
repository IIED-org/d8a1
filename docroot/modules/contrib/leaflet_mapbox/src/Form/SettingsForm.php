<?php

namespace Drupal\leaflet_mapbox\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'leaflet_mapbox_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('leaflet_mapbox.settings');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Map label'),
      '#required' => TRUE,
      '#default_value' => $config->get('label'),
      '#description' => t('Give your map a name, this will be the name shown on the display options form.'),
    ];
    $form['api_version'] = [
      '#type' => 'select',
      '#title' => t('API version'),
      '#required' => TRUE,
      '#default_value' => $config->get('api_version'),
      '#options' => [
        '3' => t('3 (Mapbox Studio Classic)'),
        '4' => t('4 (Mapbox Studio)'),
      ],
    ];

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => t('Map code'),
      '#default_value' => $config->get('code'),
      '#description' => t('This code is obtained from Mapbox by clicking on the mapbox.js button after publishing your map'),
      '#states' => [
        'visible' => [
          ':input[name="api_version"]' => ['value' => '3'],
        ],
        'required' => [
          ':input[name="api_version"]' => ['value' => '3'],
        ],
      ],
    ];

    $form['style_url'] = [
      '#type' => 'textfield',
      '#title' => t('Style URL'),
      '#default_value' => $config->get('style_url'),
      '#description' => t('Copy and paste the style URL. Example: %url.', [
        '%url' => 'mapbox://styles/johndoe/erl4zrwto008ob3f2ijepsbszg',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="api_version"]' => ['value' => '4'],
        ],
        'required' => [
          ':input[name="api_version"]' => ['value' => '4'],
        ],
      ],
    ];

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => t('Map access token'),
      '#required' => TRUE,
      '#default_value' => $config->get('token'),
      '#description' => t('You will find this in the Mapbox user account settings'),
    ];

    $form['zoomlevel'] = [
      '#type' => 'textfield',
      '#title' => t('Zoom Level'),
      '#required' => TRUE,
      '#default_value' => $config->get('zoomlevel'),
      '#description' => t('You must clear the site caches after changing this value or wait for the caches to expire before this change shows'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => t('Map description'),
      '#default_value' => $config->get('description'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    $this->config('leaflet_mapbox.settings')
      ->set('label', $values['label'])
      ->set('api_version', $values['api_version'])
      ->set('code', $values['code'])
      ->set('style_url', $values['style_url'])
      ->set('token', $values['token'])
      ->set('zoomlevel', $values['zoomlevel'])
      ->set('description', $values['description'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['leaflet_mapbox.settings'];
  }

}
