<?php

namespace Drupal\form_mode_manager\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\form_mode_manager\FormModeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base for implementing system configuration forms for Form Mode Manager.
 *
 * This abstract class allows interaction with all entities and form modes,
 * without writing redundant code to loop through each compatible entity,
 * with the form mode manager and its associated form modes.
 */
abstract class FormModeManagerFormBase extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The settings object.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The form_mode_manager service.
   *
   * @var \Drupal\form_mode_manager\FormModeManager
   */
  protected $formModeManager;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The form modes list to exclude are used or ignored.
   *
   * @var bool
   */
  protected $ignoreExcluded;

  /**
   * Ignore Active display checks from list.
   *
   * @var bool
   */
  protected $ignoreActiveDisplay;

  /**
   * Constructs a CropWidgetForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\form_mode_manager\FormModeManagerInterface $form_mode_manager
   *   The form_mode_manager service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, EntityDisplayRepositoryInterface $entity_display_repository, FormModeManagerInterface $form_mode_manager, CacheTagsInvalidatorInterface $cache_tags_invalidator, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->settings = $this->getConfig();
    $this->entityDisplayRepository = $entity_display_repository;
    $this->formModeManager = $form_mode_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->entityTypeManager = $entity_type_manager;
    $this->ignoreExcluded = FALSE;
    $this->ignoreActiveDisplay = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_display.repository'),
      $container->get('form_mode.manager'),
      $container->get('cache_tags.invalidator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Retrieve the current class editable config name.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The setting object.
   */
  protected function getConfig() {
    return $this->config(current($this->getEditableConfigNames()));
  }

  /**
   * Build Form Mode Manager settings form for each entity and form modes.
   *
   * This method implement use abstract buildFormPerEntity &,
   * buildFormPerFormMode to take control of element you need for each,
   * compatible entities and form modes used by Form Mode Manager module.
   * You can use one or all methods to applies your own logic on all,
   * entities/modes you need without code duplication.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The structure of the form populated.
   */
  public function buildFormModeForm(array &$form) {
    $form_modes = $this->formModeManager->getAllFormModesDefinitions($this->ignoreExcluded, $this->ignoreActiveDisplay);

    if (empty($form_modes)) {
      $form['empty']['#markup'] = $this->t('There were no Form Modes activated in manage form display. Please via Administration > Structure > Display modes.');

      return $form;
    }

    foreach (array_keys($form_modes) as $entity_type_id) {
      $this->buildFormPerEntity($form, $form_modes, $entity_type_id);
      foreach ($form_modes[$entity_type_id] as $form_mode) {
        $this->buildFormPerFormMode($form, $form_mode, $entity_type_id);
      }
    }

    return $form;
  }

  /**
   * Set Form Mode Manager settings from form for each entity and form modes.
   *
   * This method implement use abstract buildSettingsPerEntity &,
   * buildSettingsPerFormMode to take control of element you need,
   * to save for all compatible entities and/or form modes.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function setFormModeFormSettings(FormStateInterface $form_state): void {
    $form_modes = $this->formModeManager->getAllFormModesDefinitions($this->ignoreExcluded, $this->ignoreActiveDisplay);

    if (empty($form_modes)) {
      return;
    }

    foreach (array_keys($form_modes) as $entity_type_id) {
      $this->setSettingsPerEntity($form_state, $form_modes, $entity_type_id);
      foreach ($form_modes[$entity_type_id] as $form_mode) {
        $this->setSettingsPerFormMode($form_state, $form_mode, $entity_type_id);
      }
    }
  }

  /**
   * Build form element per compatible entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_modes
   *   The form modes collection for given entity type.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   *
   * @return string
   *   The name of the theme
   */
  abstract public function buildFormPerEntity(array &$form, array $form_modes, $entity_type_id);

  /**
   * Build form element per form modes linked by given entity type.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_mode
   *   The form mode definition.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   *
   * @return $this|false
   *   The form Object.
   */
  abstract public function buildFormPerFormMode(array &$form, array $form_mode, $entity_type_id);

  /**
   * Set settings per compatible entities.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form_modes
   *   The form modes collection for given entity type.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   */
  abstract public function setSettingsPerEntity(FormStateInterface $form_state, array $form_modes, $entity_type_id);

  /**
   * Set settings per form modes for a given entity type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form_mode
   *   The form mode definition.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   */
  abstract public function setSettingsPerFormMode(FormStateInterface $form_state, array $form_mode, $entity_type_id): void;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $this->buildFormModeForm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->setFormModeFormSettings($form_state);
    $this->settings->save();
  }

}
