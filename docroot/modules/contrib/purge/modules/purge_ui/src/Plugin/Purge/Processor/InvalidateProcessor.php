<?php

declare(strict_types=1);

namespace Drupal\purge_ui\Plugin\Purge\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeProcessor;
use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Processor for \Drupal\purge_ui\Form\PurgerInvalidateForm.
 */
#[PurgeProcessor(
  id: 'purge_ui_invalidate',
  label: new TranslatableMarkup('Direct invalidate'),
  description: new TranslatableMarkup("Lets administrators invalidate arbitrary items directly from the purgers list, without using the queue."),
  enable_by_default: TRUE,
  configform: '',
)]
final class InvalidateProcessor extends ProcessorBase implements ProcessorInterface {

}
