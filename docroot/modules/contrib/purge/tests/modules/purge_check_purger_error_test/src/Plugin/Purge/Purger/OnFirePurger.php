<?php

declare(strict_types=1);

namespace Drupal\purge_check_purger_error_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;

/**
 * Test purger that must never be invoked, its diagnostic check always errors.
 */
#[PurgePurger(
  id: "onfire",
  label: new TranslatableMarkup("Purger on fire"),
  configform: "",
  cooldown_time: 0.0,
  description: new TranslatableMarkup("Test purger blocked by a diagnostic error."),
  multi_instance: FALSE,
  types: ["everything", "tag"]
)]
final class OnFirePurger extends PurgerBase implements PurgerInterface {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    throw new \LogicException('The onfire purger must not be invoked while its diagnostic check reports an error.');
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
  public function getTimeHint() {
    return 1.0;
  }

}
