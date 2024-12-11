<?php

namespace Drupal\form_mode_manager;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\content_translation\Controller\ContentTranslationController;

/**
 * Abstract Factory to generate object used by routing of Form Mode Manager.
 *
 * This Factory are responsible to generate objects specific for each entities,
 * variations. In drupal we have few and unpredictable variants of entities,
 * this factory make a very generic object to be used by controller to make,
 * add/edit/add_page/add_admin Entity forms without duplicated code.
 * The most common variant of entity are Entities with bundles and entities,
 * without bundles (named unbundled in that module). Another case for Taxonomy,
 * are a good example of why this Factory exists because that entity haven't,
 * the common entityKey 'type' but use 'vid' instead, if that transverse,
 * module need to work in every cases we need to assume that variant by other,
 * way to use ton of code complexity.
 */
abstract class AbstractEntityFormModesFactory implements EntityFormModeManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a EntityFormModeController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\form_mode_manager\FormModeManagerInterface $formModeManager
   *   The form mode manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder service.
   * @param \Drupal\form_mode_manager\EntityRoutingMapManager $entityRoutingMap
   *   Plugin EntityRoutingMap to retrieve entity form operation routes.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected AccountInterface $account,
    protected FormModeManagerInterface $formModeManager,
    protected EntityFormBuilderInterface $entityFormBuilder,
    protected EntityRoutingMapManager $entityRoutingMap,
    protected FormBuilderInterface $formBuilder,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClassResolverInterface $classResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  abstract public function addPage(RouteMatchInterface $route_match);

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  abstract public function getEntityTypeFromRouteMatch(RouteMatchInterface $route_match);

  /**
   * Provides the entity submission form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  abstract public function getEntity(RouteMatchInterface $route_match);

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  abstract public function getEntityFromRouteMatch(RouteMatchInterface $route_match);

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If form mode is active, isAllowed() will be TRUE, otherwise isForbidden()
   *   will be TRUE.
   */
  public function checkAccess(RouteMatchInterface $route_match) {
    $route_object = $route_match->getRouteObject();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getEntityFromRouteMatch($route_match);
    $bundle_id = $this->getBundleEntityTypeId($entity, $route_match);
    $form_mode_id = $route_object->getDefault('_entity_form');
    $cache_tags = $this->formModeManager->getListCacheTags();

    // When we need to check access for actions links,
    // we don't have entity to load.
    if ($entity instanceof EntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
    }
    else {
      $entity_type_id = $route_object->getOption('_form_mode_manager_entity_type_id');
    }

    $operation = $this->getFormModeOperationName($this->formModeManager->getFormModeMachineName($form_mode_id));
    $is_active_form_mode = $this->formModeManager->isActive($entity_type_id, $bundle_id, $operation);

    return AccessResult::allowedIf($is_active_form_mode)
      ->addCacheTags($cache_tags)
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @return string
   *   The bundle entity type id or entity type id of entity.
   */
  public function getBundleEntityTypeId(EntityInterface $entity, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    $entity_type_id = $route->getOption('_form_mode_manager_entity_type_id');

    if (method_exists($entity, 'bundle') && !empty($entity->bundle())) {
      return $entity->bundle();
    }

    return $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAdd(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $operation = $this->getOperation($route_match, $entity->getEntityTypeId());

    if ($entity instanceof EntityInterface) {
      return $this->getForm($entity, $operation);
    }

    throw new \Exception("Entity retrieve from route isn't an instance of EntityInterface.");
  }

  /**
   * {@inheritdoc}
   */
  public function entityTranslationAdd(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $operation = $this->getOperation($route_match, $entity->getEntityTypeId());

    if ($entity instanceof ContentEntityInterface) {
      $source = $route_match->getParameter('source');
      $target = $route_match->getParameter('target');
      /** @var \Drupal\content_translation\Controller\ContentTranslationController $content_translation_controller */
      $content_translation_controller = $this->classResolver->getInstanceFromDefinition(ContentTranslationController::class);

      // Duplicates \Drupal\content_translation\Controller\ContentTranslationController::add().
      if (!$entity->isDefaultRevision() && ContentTranslationManager::isPendingRevisionSupportEnabled($entity->getEntityTypeId(), $entity->bundle())) {
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
        $revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $source->getId());
        if ($revision_id != $entity->getRevisionId()) {
          $entity = $storage->loadRevision($revision_id);
        }
      }

      $content_translation_controller->prepareTranslation($entity, $source, $target);

      $form_state_additions = [];
      $form_state_additions['langcode'] = $target->getId();
      $form_state_additions['content_translation']['source'] = $source;
      $form_state_additions['content_translation']['target'] = $target;
      $form_state_additions['content_translation']['translation_form'] = !$entity->access('update');

      return $this->getForm($entity, $operation, $form_state_additions);
    }

    throw new \Exception("Entity retrieve from route isn't an instance of EntityInterface.");
  }

  /**
   * {@inheritdoc}
   */
  public function entityEdit(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $operation = $this->getOperation($route_match, $entity->getEntityTypeId(), 'edit');

    if ($entity instanceof EntityInterface) {
      return $this->getForm($entity, $operation);
    }

    throw new \Exception("Entity retrieve from route isn't an instance of EntityInterface.");
  }

  /**
   * {@inheritdoc}
   *
   * @return string
   *   The title context for "add" form.
   */
  public function addPageTitle(RouteMatchInterface $route_match) {
    return $this->pageTitle($route_match, $this->t('Create'));
  }

  /**
   * {@inheritdoc}
   *
   * @return string
   *   The title context for "edit" form.
   */
  public function editPageTitle(RouteMatchInterface $route_match) {
    return $this->pageTitle($route_match, $this->t('Edit'));
  }

  /**
   * The _title_callback for the entity.add routes.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param string $operation
   *   Name of current context operation to display title (create/edit).
   *
   * @return string
   *   The page title.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function pageTitle(RouteMatchInterface $route_match, $operation) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity_properties = $this->getEntityTypeFromRouteMatch($route_match);
    $form_mode_label = $route_match->getRouteObject()
      ->getOption('parameters')['form_mode']['label'];
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getStorage($entity_properties['entity_type_id'])->getEntityType();
    $name = strtolower($entity_type->getLabel());

    if (!empty($entity_properties['bundle'])) {
      $name = $entity_properties['bundle'];
    }

    return $this->t('@op @name as @form_mode_label', [
      '@name' => $name,
      '@form_mode_label' => $form_mode_label,
      '@op' => $operation,
    ]);
  }

  /**
   * Retrieve the operation (form mode) name in edit context.
   *
   * In Form Mode Manager all edit routes use a contextual FormClass to provide,
   * a FormClass handler different by context (add/edit).
   *
   * @param string $operation
   *   The form mode id with contextual prefix.
   *
   * @return string
   *   The name of default fallback operation.
   */
  public function getFormModeOperationName($operation) {
    return preg_replace('/^(' . FormModeManagerInterface::EDIT_PREFIX . ')|(' . FormModeManagerInterface::ADD_PREFIX . ')/', '', $operation);
  }

  /**
   * Return the correct form mode name for given contexts ($op).
   *
   * To separate things Form mode manager need to contextualize form mode by,
   * operation (edit). Because this module permit to make difference and,
   * override FormClass used by add/edit operation. In drupal add and edit,
   * are declared in annotation at EntityPlugin basis but this module make,
   * possible to change that annotation and use form mode specifically,
   * for one form mode.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param string $entity_type_id
   *   The type of $entity; e.g. 'node' or 'user'.
   * @param string $op
   *   The entity form operation name.
   *
   * @return string
   *   The correct form mode name depends of given operation.
   *
   * @throws \Exception
   *   If an invalid entity is retrieving from the route object.
   */
  public function getOperation(RouteMatchInterface $route_match, $entity_type_id, $op = '') {
    $form_mode_id = $this->formModeManager->getFormModeMachineName($route_match->getRouteObject()->getOption('parameters')['form_mode']['id']);
    /** @var \Drupal\form_mode_manager\EntityRoutingMapBase $entity_routes_infos */
    $entity_routes_infos = $this->entityRoutingMap->createInstance($entity_type_id, ['entityTypeId' => $entity_type_id])->getPluginDefinition();

    if ($op === 'edit') {
      return empty($form_mode_id) ? $entity_routes_infos->getDefaultFormClass() : FormModeManagerInterface::EDIT_PREFIX . $form_mode_id;
    }

    return empty($form_mode_id) ? $entity_routes_infos->getDefaultFormClass() : FormModeManagerInterface::ADD_PREFIX . $form_mode_id;
  }

  /**
   * Gets the built and processed entity form for the given entity.
   *
   * This method are very similar to EntityFormBuilderInterface::getForm,
   * for this module we need to add two form handler by form mode eg :
   * form_mode_1 => EntityFormClass
   * edit_form_mode_1 => EntityFormClass
   * to provide ability to define different EntityForm class for form,
   * for add/edit (or others) contexts.
   * Actually EntityFormBuilderInterface::getForm are designed to only have,
   * one operation (form mode) by action (add/edit/xxxx).
   *
   * In that method we use $operation parameter to retrieve the correct,
   * FormObject with our context prefixed by 'edit_' or not and in next step we,
   * set the correct Operation form with only the form mode name,
   * with ->setOperation() method onto FormObject.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be created or edited.
   * @param string $operation
   *   (optional) The operation identifying the form variation to be returned.
   *   Defaults to 'default'. This is typically used in routing:.
   * @param array $form_state_additions
   *   (optional) An associative array used to build the current state of the
   *   form. Use this to pass additional information to the form, such as the
   *   langcode. Defaults to an empty array.
   *
   * @return array
   *   The entity Form.
   *
   * @throws \Drupal\Core\Form\FormAjaxException
   *   Thrown when a form is triggered via an AJAX submission. It will be
   *   handled by \Drupal\Core\Form\EventSubscriber\FormAjaxSubscriber.
   * @throws \Drupal\Core\Form\EnforcedResponseException
   *   Thrown when a form builder returns a response directly, usually a
   *   \Symfony\Component\HttpFoundation\RedirectResponse. It will be handled by
   *   \Drupal\Core\EventSubscriber\EnforcedFormResponseSubscriber.
   */
  public function getForm(EntityInterface $entity, $operation = 'default', array $form_state_additions = []) {
    $form_object = $this->entityTypeManager->getFormObject($entity->getEntityTypeId(), $operation);
    $form_object->setEntity($entity)
      ->setOperation($this->getFormModeOperationName($operation));

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
