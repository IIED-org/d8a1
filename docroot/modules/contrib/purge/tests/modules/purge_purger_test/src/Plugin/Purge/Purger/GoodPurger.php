<?php

namespace Drupal\purge_purger_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * A purger that always succeeds.
 */
#[PurgePurger(
  id: "good",
  label: new TranslatableMarkup("Good Purger"),
  configform: "",
  cooldown_time: 1.0,
  description: new TranslatableMarkup("A purger that always succeeds."),
  multi_instance: FALSE,
  types: ["tag", "regex", "url", "path", "domain", "everything", "wildcardpath", "wildcardurl"]
)]
class GoodPurger extends NullPurgerBase {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
  }

}
