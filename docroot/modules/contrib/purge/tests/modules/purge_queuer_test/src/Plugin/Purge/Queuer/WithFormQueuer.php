<?php

namespace Drupal\purge_queuer_test\Plugin\Purge\Queuer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueuer;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Test queuer with a configuration form.
 */
#[PurgeQueuer(
  id: "withform",
  label: new TranslatableMarkup("Queuer with form"),
  description: new TranslatableMarkup("Test queuer with a configuration form."),
  enable_by_default: FALSE,
  configform: "\\Drupal\\purge_queuer_test\\Form\\QueuerConfigForm"
)]
class WithFormQueuer extends QueuerBase implements QueuerInterface {}
