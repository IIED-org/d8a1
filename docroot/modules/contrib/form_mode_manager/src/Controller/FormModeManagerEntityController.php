<?php

namespace Drupal\form_mode_manager\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\form_mode_manager\ComplexEntityFormModes;
use Drupal\form_mode_manager\EntityFormModeManagerInterface;
use Drupal\form_mode_manager\EntityRoutingMapManager;
use Drupal\form_mode_manager\FormModeManagerInterface;
use Drupal\form_mode_manager\MediaEntityFormModes;
use Drupal\form_mode_manager\SimpleEntityFormModes;
use Drupal\form_mode_manager\TaxonomyEntityFormModes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic Controller for entity using form mode manager routing.
 *
 * This controller are very transverse and use an Abstract Factory to build,
 * objects compatible with all ContentEntities. This controller are linked by,
 * Abstract Factory by EntityFormModeManagerInterface each methods in that,
 * interface are called by routing.
 */
class FormModeManagerEntityController implements EntityFormModeManagerInterface, ContainerInjectionInterface {

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('form_mode.manager'),
      $container->get('entity.form_builder'),
      $container->get('plugin.manager.entity_routing_map'),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('class_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function addPageTitle(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function editPageTitle(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function entityAdd(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function entityEdit(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public function entityTranslationAdd(RouteMatchInterface $route_match) {
    return $this->getEntityControllerResponse(__FUNCTION__, $route_match);
  }

  /**
   * Instantiate correct objects depending entities.
   *
   * Contain all the logic to use the abstract factory and call,
   * correct entityFormMode object depending entity_type using bundles,
   * or if the entity need to be processed specifically like Taxonomy.
   *
   * All of children object share EntityFormModeManagerInterface to make sure,
   * methods are used by factory.
   *
   * @param string $method
   *   Name of the method we need to build.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return \Drupal\form_mode_manager\EntityFormModeManagerInterface
   *   An instance of correct controller object.
   *
   * @throws \Exception
   *   Thrown when specified method was not found.
   */
  public function getEntityControllerResponse($method, RouteMatchInterface $route_match) {
    $entity_type_id = $route_match->getRouteObject()
      ->getOption('_form_mode_manager_entity_type_id');
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    // Entities without bundles need to be flagged 'unbundled_entity'.
    $entity_type_id = $this->bundledEntity($entity_storage) ? $entity_type_id : 'unbundled_entity';
    $controller_object = $this->getEntityControllerObject($entity_type_id);

    if (!method_exists($controller_object, $method)) {
      throw new \Exception('Specified ' . $method . ' method not found.');
    }
    return $controller_object->{$method}($route_match);
  }

  /**
   * Get the correct controller object Factory depending kind of entity.
   *
   * @param string $entity_type_id
   *   The name of entity type.
   *
   * @return \Drupal\form_mode_manager\EntityFormModeManagerInterface
   *   An instance of correct controller object.
   */
  public function getEntityControllerObject($entity_type_id) {
    switch ($entity_type_id) {
      case 'unbundled_entity':
        $object = new SimpleEntityFormModes(
          $this->renderer,
          $this->account,
          $this->formModeManager,
          $this->entityFormBuilder,
          $this->entityRoutingMap,
          $this->formBuilder,
          $this->entityTypeManager,
          $this->classResolver,
        );

        break;

      case 'taxonomy_term':
        $object = new TaxonomyEntityFormModes(
          $this->renderer,
          $this->account,
          $this->formModeManager,
          $this->entityFormBuilder,
          $this->entityRoutingMap,
          $this->formBuilder,
          $this->entityTypeManager,
          $this->classResolver,
        );

        break;

      case 'media':
        $object = new MediaEntityFormModes(
          $this->renderer,
          $this->account,
          $this->formModeManager,
          $this->entityFormBuilder,
          $this->entityRoutingMap,
          $this->formBuilder,
          $this->entityTypeManager,
          $this->classResolver,
        );

        break;

      default:
        $object = new ComplexEntityFormModes(
          $this->renderer,
          $this->account,
          $this->formModeManager,
          $this->entityFormBuilder,
          $this->entityRoutingMap,
          $this->formBuilder,
          $this->entityTypeManager,
          $this->classResolver,
        );
        break;
    }

    return $object;
  }

  /**
   * Evaluate if current entity have bundles or not.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   *
   * @return string
   *   The bundle key if entity has bundles or empty.
   */
  public function bundledEntity(EntityStorageInterface $entity_storage) {
    return $entity_storage->getEntityType()->getKey('bundle');
  }

}
