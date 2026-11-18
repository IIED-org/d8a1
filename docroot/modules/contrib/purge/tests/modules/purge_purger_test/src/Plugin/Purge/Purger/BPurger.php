<?php

namespace Drupal\purge_purger_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;

/**
 * Test purger B.
 */
#[PurgePurger(
  id: "b",
  label: new TranslatableMarkup("Purger B"),
  configform: "",
  cooldown_time: 0.5,
  description: new TranslatableMarkup("Test purger B."),
  multi_instance: FALSE,
  types: ["regex", "url"]
)]
class BPurger extends NullPurgerBase {}
