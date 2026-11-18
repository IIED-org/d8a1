<?php

namespace Drupal\purge_queuer_test\Plugin\Purge\Queuer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueuer;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Test queuer B.
 */
#[PurgeQueuer(
  id: "b",
  label: new TranslatableMarkup("Queuer B"),
  description: new TranslatableMarkup("Test queuer B."),
  enable_by_default: TRUE,
  configform: ""
)]
class BQueuer extends QueuerBase implements QueuerInterface {}
