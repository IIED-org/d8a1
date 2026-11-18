<?php

namespace Drupal\purge_processor_test\Plugin\Purge\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeProcessor;
use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Test processor A.
 */
#[PurgeProcessor(
  id: "a",
  label: new TranslatableMarkup("Processor A"),
  description: new TranslatableMarkup("Test processor A."),
  enable_by_default: TRUE,
  configform: ""
)]
class AProcessor extends ProcessorBase implements ProcessorInterface {

}
