<?php

namespace Drupal\form_mode_manager;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\form_mode_manager\Routing\EventSubscriber\FormModesSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manipulates Form Alter information.
 *
 * This class contains primarily bridged hooks for form.
 */
class FormAlter implements ContainerInjectionInterface {

  /**
   * FormAlter constructor.
   *
   * @param \Drupal\form_mode_manager\FormModeManagerInterface $formModeManager
   *   The form mode manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected FormModeManagerInterface $formModeManager,
    protected RouteMatchInterface $routeMatch,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_mode.manager'),
      $container->get('current_route_match'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Allow to use user_registrationpassword form_alter with FormModeManager.
   *
   * This is an alter hook bridge.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The Form ID.
   *
   * @see hook_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    if ($this->candidateToAlterUserForm()) {
      $this->userRegistrationPasswordFormAlter($form, $form_state, $form_id);
    }
    if ($this->candidateToAlterContentTranslationForm($form_state)) {
      $this->contentTranslationFormAlter($form, $form_state, $form_id);
    }
  }

  /**
   * Applies the content_translation_form_alter() to additional form modes.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The Form ID.
   */
  public function contentTranslationFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form_object->getEntity();
    $op = $form_object->getOperation();

    // Add the form mode manager form modes to the defaults allowed
    // by content_translation.
    $operations = $this->formModeManager->getFormModesIdByEntity($entity->getEntityTypeId());
    if ($entity instanceof ContentEntityInterface && $entity->isTranslatable() && count($entity->getTranslationLanguages()) > 1 && in_array($op, $operations, TRUE)) {
      $controller = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'translation');
      $controller->entityFormAlter($form, $form_state, $entity);

      // @todo Move the following lines to the code generating the property form
      //   elements once we have an official #multilingual FAPI key.
      $translations = $entity->getTranslationLanguages();
      $form_langcode = $form_object->getFormLangcode($form_state);

      // Handle fields shared between translations when there is at least one
      // translation available or a new one is being created.
      if (!$entity->isNew() && (!isset($translations[$form_langcode]) || count($translations) > 1)) {
        foreach ($entity->getFieldDefinitions() as $field_name => $definition) {

          // Allow the widget to define if it should be treated as multilingual
          // by respecting an already set #multilingual key.
          if (isset($form[$field_name]) && !isset($form[$field_name]['#multilingual'])) {
            $form[$field_name]['#multilingual'] = $definition->isTranslatable();
          }
        }
      }

      // The footer region, if defined, may contain multilingual widgets so we
      // need to always display it.
      if (isset($form['footer'])) {
        $form['footer']['#multilingual'] = TRUE;
      }
    }
  }

  /**
   * Applies the user_register_form_alter on form_mode_manager register routes.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The Form ID.
   */
  public function userRegistrationPasswordFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Symfony\Component\Routing\Route $routeObject */
    $routeObject = $this->routeMatch->getRouteObject();
    $dynamicFormId = str_replace('.', '_', $routeObject->getDefault('_entity_form')) . '_form';

    // Prevent cases of register users create/register operations.
    if (FormModesSubscriber::isEditRoute($routeObject)) {
      return;
    }

    if (
      $this->appliesUserRegistrationPasswordFormAlter($form_id, $dynamicFormId)
      && function_exists('user_registrationpassword_form_user_register_form_alter')
    ) {
      // phpcs:disable
      // Ignore because this function may not exist but we are
      // checking it as a condition in the above if statement to ensure it
      // does actually exist.
      user_registrationpassword_form_user_register_form_alter($form, $form_state, $form_id);
      // phpcs:enable
    }
  }

  /**
   * Evaluate if current form can applies the user_registrationpassword hook.
   *
   * @param string $form_id
   *   The Form ID.
   * @param string $dynamicFormId
   *   The Form ID calculated via routing entity_form parameters.
   *
   * @return bool
   *   True if the hook can be applied of False if not.
   */
  public function appliesUserRegistrationPasswordFormAlter($form_id, $dynamicFormId) {
    $routeObject = $this->routeMatch->getRouteObject();
    $formModeMachineName = $this->formModeManager->getFormModeMachineName($routeObject->getDefault('_entity_form'));

    return $this->formModeManager->isActive('user', NULL, $formModeMachineName) && $dynamicFormId === $form_id;
  }

  /**
   * Evaluate if FormModeManager do applies this formAlter for registration.
   *
   * @return bool
   *   True if user_registration is enabled and this user route are FMM route.
   */
  public function candidateToAlterUserForm() {
    $routeObject = $this->routeMatch->getRouteObject();
    if (NULL === $routeObject) {
      return FALSE;
    }

    return $this->moduleHandler->moduleExists('user_registrationpassword') && $routeObject->getOption('_form_mode_manager_entity_type_id') === "user";
  }

  /**
   * Evaluate if this form alter should be applied for content translation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   True if the form is a content entity form interface.
   */
  public function candidateToAlterContentTranslationForm(FormStateInterface $form_state) {
    return $form_state->getFormObject() instanceof ContentEntityFormInterface;
  }

}
