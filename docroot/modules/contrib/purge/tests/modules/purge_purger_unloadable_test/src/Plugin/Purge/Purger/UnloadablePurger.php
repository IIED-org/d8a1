<?php

declare(strict_types=1);

namespace Drupal\purge_purger_unloadable_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A purger that cannot be created because a service it needs is missing.
 *
 * This mirrors a purger whose module is being uninstalled: its definition is
 * still discovered while the services it depends on are already gone.
 */
#[PurgePurger(
  id: "unloadable",
  label: new TranslatableMarkup("Unloadable Purger"),
  configform: "",
  cooldown_time: 0.0,
  description: new TranslatableMarkup("A purger that requests a missing service."),
  multi_instance: FALSE,
  types: ["tag"]
)]
final class UnloadablePurger extends PurgerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $container->get('purge_purger_unloadable_test.missing_service');
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
  }

}
