<?php

namespace Drupal\form_mode_manager;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines an interface to return information on form modes.
 */
interface FormModeManagerInterface {

  /**
   * The add identifier for FMM FormClass.
   */
  const ADD_PREFIX = 'fmm_';

  /**
   * The add identifier for FMM FormClass.
   */
  const EDIT_PREFIX = 'fmm_edit_';

  /**
   * Returns entity (form) displays for the current entity display type.
   *
   * @param string $entity_type_id
   *   The entity type ID to check active modes.
   *
   * @return array
   *   The Display mode id for defined entity_type_id.
   */
  public function getActiveDisplays($entity_type_id);

  /**
   * Gets the path of specified entity type for a form mode.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $form_mode_id
   *   The form mode machine name.
   *
   * @return string
   *   The path to use the specified form mode.
   */
  public function getFormModeManagerPath(EntityTypeInterface $entity_type, $form_mode_id);

  /**
   * Gets all form modes id for a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return array
   *   An array contain all available form mode machine name.
   */
  public function getFormModesIdByEntity($entity_type_id);

  /**
   * Gets the entity form mode info for a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type.
   *
   * @return array
   *   An array contain all available form mode machine name.
   */
  public function getFormModesByEntity($entity_type_id);

  /**
   * Gets the entity form mode info for all entity types used.
   *
   * @param bool $ignore_excluded
   *   Joker to determine if form modes to exclude list are used or ignored.
   * @param bool $ignore_active_display
   *   Flag to ignore if a form mode is used in bundle display.
   *   This parameter doesn't not be activated in entityTypeAlter,
   *   context caused by getStorage() call.
   *
   * @return array
   *   The collection without unneeded form modes.
   */
  public function getAllFormModesDefinitions($ignore_excluded = FALSE, $ignore_active_display = FALSE);

  /**
   * Filter a form mode collection to exclude all desired form mode id.
   *
   * @param array $form_mode
   *   A form mode collection to be filtered.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   * @param bool $ignore_excluded
   *   Joker to determine if form modes to exclude list are used or ignored.
   */
  public function filterExcludedFormModes(array &$form_mode, $entity_type_id, $ignore_excluded): void;

  /**
   * Filter form mode collection depending activation in bundle.
   *
   * The getStorage() onto 'entity_form_display' can't be played ,
   * in specific cases eg: entityTypeAlter because that cause an,
   * endless loop caused by Annotation::reset during instantiation of plugin.
   *
   * @param array $form_mode
   *   A form mode collection to be filtered.
   * @param string $entity_type_id
   *   The entity type ID of entity.
   */
  public function filterInactiveDisplay(array &$form_mode, $entity_type_id): void;

  /**
   * Retrieve the list of form_modes unavailable to expose by Form Mode Manager.
   *
   * @param string $entity_type_id
   *   The entity type ID of entity.
   *
   * @return array[]
   *   The list of form modes to exclude.
   */
  public function getFormModeExcluded($entity_type_id);

  /**
   * Determine if form_mode definition passed is valid.
   *
   * @param array $form_mode
   *   A form mode definition to fetch.
   *
   * @return bool
   *   True if Form mode has minimal information or false.
   */
  public function isValidFormMode(array $form_mode);

  /**
   * Evaluate if current form_mode is candidate to be filtered or not.
   *
   * @param array $form_mode
   *   A form mode definition.
   * @param string $entity_type_id
   *   The entity type id.
   * @param bool $ignore_excluded
   *   Joker to determine if form modes to exclude list are used or ignored.
   *
   * @return bool
   *   True if form mode is candidate to be excluded or False if not.
   */
  public function candidateToExclude(array $form_mode, $entity_type_id, $ignore_excluded);

  /**
   * Gets the entity form mode info for a specific bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle_id
   *   Identifier of bundle.
   *
   * @return array|null
   *   The form mode activated for defined bundle.
   */
  public function getActiveDisplaysByBundle($entity_type_id, $bundle_id);

  /**
   * Determine if a form mode is activated onto bundle of specific entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle_id
   *   (Optional) Name of bundle for current entity.
   * @param string $form_mode_machine_name
   *   Machine name of form mode.
   *
   * @return bool
   *   True if FormMode is activated on needed bundle.
   */
  public function isActive($entity_type_id, $bundle_id, $form_mode_machine_name);

  /**
   * Retrieve Form Mode Machine Name from the form mode id.
   *
   * @param string $form_mode_id
   *   Identifier of form mode prefixed by entity type id.
   *
   * @return string
   *   The form mode machine name without prefix of,
   *   entity (entity.form_mode_name).
   */
  public function getFormModeMachineName($form_mode_id);

  /**
   * The list cache tags associated with form display entities.
   *
   * Enables code listing entities of this type to ensure that newly created
   * entities show up immediately. This is wrapped by Form Mode Manager to
   * permit a more precise cache strategy and allow the form mode manager to
   * add its permissions tags.
   *
   * @return string[]
   *   List of cache Tags to invalidate.
   */
  public function getListCacheTags();

  /**
   * Determine Local tasks position for an entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return bool
   *   True if tasks does display at primary position.
   */
  public function tasksIsPrimary($entity_type_id);

  /**
   * Determine if current entity_type has one bundle implement this mode.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $form_mode_id
   *   Identifier of form mode prefixed by entity type id.
   *
   * @return bool
   *   True if tasks does display at primary position.
   */
  public function hasActiveFormMode($entity_type, $form_mode_id);

  /**
   * Set all entity handlers needed by form mode manager on entity type basis.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_definition
   *   The entity type to alter.
   */
  public function setEntityHandlersPerFormModes(EntityTypeInterface $entity_definition);

  /**
   * Set new FormClass handler per form modes keyed by form mode name.
   *
   * This setter are the best way to alter the basic FormClass,
   * for specific operation (form mode) @see http://bit.ly/2sL5L7W .
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_definition
   *   The entity type to alter.
   * @param string $form_mode_name
   *   The form mode human name used by current entity definition.
   */
  public function setFormClassPerFormModes(EntityTypeInterface $entity_definition, $form_mode_name);

  /**
   * Set new LinkTemplate handler on entity definition basis.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_definition
   *   The entity type to alter.
   * @param string $form_mode_name
   *   The form mode human name used by current entity definition.
   */
  public function setLinkTemplatePerFormModes(EntityTypeInterface $entity_definition, $form_mode_name);

}
