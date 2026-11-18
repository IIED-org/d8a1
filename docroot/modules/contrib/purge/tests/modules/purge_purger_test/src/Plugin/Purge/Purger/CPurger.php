<?php

namespace Drupal\purge_purger_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;

/**
 * Test purger C.
 */
#[PurgePurger(
  id: "c",
  label: new TranslatableMarkup("Purger C"),
  configform: "",
  cooldown_time: 0.0,
  description: new TranslatableMarkup("Test purger C."),
  multi_instance: FALSE,
  types: ["wildcardpath", "wildcardurl"]
)]
class CPurger extends NullPurgerBase {}
