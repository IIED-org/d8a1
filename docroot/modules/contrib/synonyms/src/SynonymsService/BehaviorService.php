<?php

namespace Drupal\synonyms\SynonymsService;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\synonyms\SynonymsService\Behavior\SynonymsBehaviorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Collect all known synonyms behavior services.
 *
 * Collect all known synonyms behavior services during dependency injection
 * container compilation.
 */
class BehaviorService implements ContainerInjectionInterface {

  /**
   * The behavior services.
   *
   * @var array
   */
  protected $behaviorServices;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * BehaviorService constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->behaviorServices = [];

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Add a new discovered behavior service.
   *
   * @param \Drupal\synonyms\SynonymsService\Behavior\SynonymsBehaviorInterface $behavior_service
   *   Behavior service object that was discovered and should be added into the
   *   list of known ones.
   * @param string $id
   *   Service ID that corresponds to this behavior service.
   */
  public function addBehaviorService(SynonymsBehaviorInterface $behavior_service, $id) {
    if (!isset($this->behaviorServices[$id])) {
      $this->behaviorServices[$id] = [
        'service' => $behavior_service,
      ];
    }
  }

  /**
   * Array of known synonyms behavior services.
   *
   * @return array
   *   The return value
   */
  public function getBehaviorServices() {
    return $this->behaviorServices;
  }

  /**
   * Get a synonyms behavior service.
   *
   * @param string $behavior_service_id
   *   ID of the service to retrieve.
   *
   * @return array
   *   Array of information about the behavior service. The array will have the
   *   following structure:
   *   - service: (SynonymsBehaviorInterface) Initiated behavior service
   */
  public function getBehaviorService($behavior_service_id) {
    return isset($this->behaviorServices[$behavior_service_id]) ? $this->behaviorServices[$behavior_service_id] : NULL;
  }

  /**
   * Get a list of enabled synonym providers for a requested synonyms behavior.
   *
   * @param string $synonyms_behavior
   *   ID of the synonyms behavior services whose enabled providers should be
   *   returned.
   * @param string $entity_type
   *   Entity type for which to conduct the search.
   * @param string|array $bundle
   *   Single bundle or an array of them for which to conduct the search. If
   *   null is given, then no restrictions are applied on bundle level.
   *
   * @return \Drupal\synonyms\Entity\Synonym[]
   *   The array of enabled synonym providers
   */
  public function getSynonymConfigEntities($synonyms_behavior, $entity_type, $bundle) {
    $entities = [];

    if (is_scalar($bundle) && !is_null($bundle)) {
      $bundle = [$bundle];
    }

    foreach ($this->entityTypeManager->getStorage('synonym')->loadMultiple() as $entity) {
      $provider_instance = $entity->getProviderPluginInstance();
      $provider_definition = $provider_instance->getPluginDefinition();
      if ($provider_definition['synonyms_behavior_service'] == $synonyms_behavior && $provider_definition['controlled_entity_type'] == $entity_type && (!is_array($bundle) || in_array($provider_definition['controlled_bundle'], $bundle))) {
        $entities[] = $entity;
      }
    }

    return $entities;
  }

}
