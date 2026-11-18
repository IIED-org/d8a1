<?php

namespace Drupal\purge_processor_test\Plugin\Purge\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeProcessor;
use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Test processor with a configuration form.
 */
#[PurgeProcessor(
  id: "withform",
  label: new TranslatableMarkup("Processor with form"),
  description: new TranslatableMarkup("Test processor with a configuration form."),
  enable_by_default: FALSE,
  configform: "\\Drupal\\purge_processor_test\\Form\\ProcessorConfigForm"
)]
class WithFormProcessor extends ProcessorBase implements ProcessorInterface {

}
