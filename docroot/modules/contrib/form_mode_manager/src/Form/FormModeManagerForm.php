<?php

namespace Drupal\form_mode_manager\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_mode_manager\FormModeManagerInterface;

/**
 * Configure Form Mode Manager common settings.
 */
class FormModeManagerForm extends FormModeManagerFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, EntityDisplayRepositoryInterface $entity_display_repository, FormModeManagerInterface $form_mode_manager, CacheTagsInvalidatorInterface $cache_tags_invalidator, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory, $typed_config_manager, $entity_display_repository, $form_mode_manager, $cache_tags_invalidator, $entity_type_manager);

    $this->ignoreExcluded = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_mode_manager_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['form_mode_manager.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if (isset($form['empty'])) {
      return $form;
    }

    $form['vertical_tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    $this->buildFormModeForm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFormPerEntity(array &$form, array $form_modes, $entity_type_id) {
    $options = array_combine(array_keys($form_modes[$entity_type_id]), array_column($form_modes[$entity_type_id], 'label'));
    $entity_label = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType()->getLabel();
    $form[$entity_type_id] = [
      '#type' => 'details',
      '#title' => $entity_label,
      '#group' => 'vertical_tabs',
    ];

    if ($options) {
      $form[$entity_type_id]['element_' . $entity_type_id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Choose which form modes you would like to exclude from being processed by the Form Mode Manager.'),
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => $this->settings->get("form_modes.{$entity_type_id}.to_exclude") ?: [],
      ];
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFormPerFormMode(array &$form, array $form_mode, $entity_type_id) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettingsPerEntity(FormStateInterface $form_state, array $form_modes, $entity_type_id) {
    $this->settings->set("form_modes.{$entity_type_id}.to_exclude", $form_state->getValue('element_' . $entity_type_id));
  }

  /**
   * {@inheritdoc}
   */
  public function setSettingsPerFormMode(FormStateInterface $form_state, array $form_mode, $entity_type_id): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->cacheTagsInvalidator->invalidateTags([
      'entity_types',
      'routes',
      'local_tasks',
      'local_task',
      'local_action',
      'rendered',
    ]);
  }

}
