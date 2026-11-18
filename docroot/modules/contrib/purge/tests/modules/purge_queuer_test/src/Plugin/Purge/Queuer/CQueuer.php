<?php

namespace Drupal\purge_queuer_test\Plugin\Purge\Queuer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueuer;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Test queuer C.
 */
#[PurgeQueuer(
  id: "c",
  label: new TranslatableMarkup("Queuer C"),
  description: new TranslatableMarkup("Test queuer C."),
  enable_by_default: FALSE,
  configform: ""
)]
class CQueuer extends QueuerBase implements QueuerInterface {}
