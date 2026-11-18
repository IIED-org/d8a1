<?php

namespace Drupal\purge_processor_test\Plugin\Purge\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeProcessor;
use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Test processor B.
 */
#[PurgeProcessor(
  id: "b",
  label: new TranslatableMarkup("Processor B"),
  description: new TranslatableMarkup("Test processor B."),
  enable_by_default: TRUE,
  configform: ""
)]
class BProcessor extends ProcessorBase implements ProcessorInterface {

}
