<?php

namespace Drupal\better_field_descriptions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the better_field_descriptions_entities settings form.
 */
class BetterFieldDescriptionsEntitiesForm extends ConfigFormBase {

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info_service) {
    parent::__construct($config_factory);
    $this->bundleInfoService = $bundle_info_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['better_field_descriptions.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormId() {
    return 'better_field_descriptions_entities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get info on bundles.
    $all_bundles = $this->bundleInfoService->getAllBundleInfo();
    // Sort into order on entity ids.
    ksort($all_bundles);

    // Get the editiable config.
    $config = $this->config('better_field_descriptions.settings');

    // Get list of entities selected for better descriptions.
    $bfde = $config->get('better_field_descriptions_entities');

    $form['descriptions'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Select entity types that should have better descriptions.'),
    ];

    $form['entities'] = [
      '#type' => 'item',
      '#prefix' => '<div id="better-descriptions-form-id-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    foreach ($all_bundles as $entity_type => $bundles) {
      $form['entities'][$entity_type] = [
        '#type' => 'checkbox',
        '#title' => $entity_type,
        '#default_value' => isset($bfde[$entity_type]),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the config settings.
    $config = $this->config('better_field_descriptions.settings');

    // We don't want our settings to contain 0-values, only selected values.
    $selected = array_filter($form_state->getValue('entities'));

    $bfde = [];
    foreach ($selected as $entity_type => $checked) {
      $bfde[$entity_type] = $entity_type;
    }

    $config->set('better_field_descriptions_entities', $bfde);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
