<?php

namespace Drupal\form_mode_manager;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for form mode manager entity routing plugin.
 *
 * This plugin is used to abstract the concepts implemented by EntityPlugin.
 * In the Entity API, we have the possibility to link entity form 'handlers' to
 * a specific FormClass, but the operation name and routes linked with it are
 * very arbitrary and unpredictable, especially in custom entities cases.
 * In this plugin, you have the possibility to map operations and
 * other useful information about an entity to reduce the complexity of
 * retrieving each possible case.
 */
abstract class EntityRoutingMapBase extends PluginBase implements EntityRoutingMapInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Plugin label.
   *
   * @var string
   */
  protected $label;

  /**
   * Default form class Definition name.
   *
   * @var string
   */
  protected $defaultFormClass;

  /**
   * Default editing form class Definition name.
   *
   * @var string
   */
  protected $editFormClass;

  /**
   * Entity type id of entity.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs display plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setTargetEntityType();
    $this->setConfiguration($configuration);
    $this->setDefaultFormClass();
    $this->setEditFormClass();
    $this->setContextualLinks();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $operation_name
   *   The name of needed operation to retrieve.
   */
  public function getOperation($operation_name) {
    if (isset($this->pluginDefinition['operations'][$operation_name])) {
      return $this->pluginDefinition['operations'][$operation_name];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations() {
    return $this->pluginDefinition['operations'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormClass() {
    return $this->defaultFormClass;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditFormClass() {
    return $this->editFormClass;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualLinks() {
    return $this->pluginDefinition['contextualLinks'];
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualLink($operation_name) {
    if (isset($this->pluginDefinition['contextualLinks'][$operation_name])) {
      return $this->pluginDefinition['contextualLinks'][$operation_name];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration += $this->defaultConfiguration();

    if ($this->getPluginId() === 'generic') {
      $this->setTargetEntityType();
      $this->setOperations();
    }

    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperations() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultFormClass() {
    $this->defaultFormClass = $this->pluginDefinition['defaultFormClass'];
  }

  /**
   * {@inheritdoc}
   */
  public function setEditFormClass() {
    $this->editFormClass = $this->pluginDefinition['editFormClass'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContextualLinks(): void {
    if ($this->doGenerateContextualLinks()) {
      $operations = [
        'edit' => "entity.{$this->targetEntityType}.edit_form",
      ];

      $this->pluginDefinition['contextualLinks'] += $operations;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityType() {
    return $this->targetEntityType;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityType() {
    if (empty($this->pluginDefinition['targetEntityType']) && !empty($this->configuration['entityTypeId'])) {
      $this->pluginDefinition['targetEntityType'] = $this->configuration['entityTypeId'];
    }

    $this->targetEntityType = $this->pluginDefinition['targetEntityType'];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Evaluate if current pluginDefinition contextualLink is applicable.
   *
   * @return bool
   *   True if we can generate the plugin definition or False if not.
   */
  private function doGenerateContextualLinks() {
    return is_array($this->pluginDefinition['contextualLinks']) && !isset($this->pluginDefinition['contextualLinks']['edit']);
  }

}
