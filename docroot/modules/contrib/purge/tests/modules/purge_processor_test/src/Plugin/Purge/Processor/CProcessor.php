<?php

namespace Drupal\purge_processor_test\Plugin\Purge\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeProcessor;
use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Test processor C.
 */
#[PurgeProcessor(
  id: "c",
  label: new TranslatableMarkup("Processor C"),
  description: new TranslatableMarkup("Test processor C."),
  enable_by_default: FALSE,
  configform: ""
)]
class CProcessor extends ProcessorBase implements ProcessorInterface {

}
