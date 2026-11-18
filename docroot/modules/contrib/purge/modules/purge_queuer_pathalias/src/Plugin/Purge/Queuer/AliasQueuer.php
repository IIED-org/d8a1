<?php

namespace Drupal\purge_queuer_pathalias\Plugin\Purge\Queuer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeQueuer;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Queues URL invalidations when path aliases change.
 */
#[PurgeQueuer(
  id: 'pathalias',
  label: new TranslatableMarkup('Path alias queuer'),
  description: new TranslatableMarkup('Queues URL invalidations when path aliases are created, changed or deleted.'),
  enable_by_default: TRUE,
  configform: '\Drupal\purge_queuer_pathalias\Form\ConfigurationForm',
)]
class AliasQueuer extends QueuerBase implements QueuerInterface {

}
