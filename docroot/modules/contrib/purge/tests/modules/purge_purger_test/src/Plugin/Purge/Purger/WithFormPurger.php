<?php

namespace Drupal\purge_purger_test\Plugin\Purge\Purger;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgePurger;

/**
 * Test PurgerWithForm.
 */
#[PurgePurger(
  id: "withform",
  label: new TranslatableMarkup("Configurable purger"),
  configform: "\\Drupal\\purge_purger_test\\Form\\PurgerConfigForm",
  cooldown_time: 0.7,
  description: new TranslatableMarkup("Test purger with a form attached."),
  multi_instance: FALSE,
  types: ["path"]
)]
class WithFormPurger extends NullPurgerBase {}
