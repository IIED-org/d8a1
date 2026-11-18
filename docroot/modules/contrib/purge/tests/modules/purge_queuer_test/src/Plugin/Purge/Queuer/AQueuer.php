<?php

namespace Drupal\purge_queuer_test\Plugin\Purge\Queuer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueuer;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Test queuer A.
 */
#[PurgeQueuer(
  id: "a",
  label: new TranslatableMarkup("Queuer A"),
  description: new TranslatableMarkup("Test queuer A."),
  enable_by_default: TRUE,
  configform: ""
)]
class AQueuer extends QueuerBase implements QueuerInterface {}
