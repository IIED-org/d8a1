<?php

namespace Drupal\purge_purger_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;

/**
 * Test purger A.
 */
#[PurgePurger(
  id: "a",
  label: new TranslatableMarkup("Purger A"),
  configform: "",
  cooldown_time: 0.2,
  description: new TranslatableMarkup("Test purger A."),
  multi_instance: FALSE,
  types: ["everything"]
)]
class APurger extends NullPurgerBase {}
