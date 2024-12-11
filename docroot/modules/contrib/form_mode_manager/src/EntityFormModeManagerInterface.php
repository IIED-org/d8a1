<?php

namespace Drupal\form_mode_manager;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Interface for managing entity form modes.
 */
interface EntityFormModeManagerInterface {

  /**
   * Displays links to add content for available entity types.
   *
   * Redirects to entity/add/[bundle] if only one bundle is available.
   */
  public function addPage(RouteMatchInterface $route_match);

  /**
   * Generates the title for the 'add' page routes.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function addPageTitle(RouteMatchInterface $route_match);

  /**
   * Checks access permissions for the form mode manager routes.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function checkAccess(RouteMatchInterface $route_match);

  /**
   * The _title_callback for the entity.add routes.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function editPageTitle(RouteMatchInterface $route_match);

  /**
   * Provides the entity add submission form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function entityAdd(RouteMatchInterface $route_match);

  /**
   * Provides the 'edit' form for an entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function entityEdit(RouteMatchInterface $route_match);

  /**
   * Provides the entity 'content_translation_add' form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function entityTranslationAdd(RouteMatchInterface $route_match);

}
