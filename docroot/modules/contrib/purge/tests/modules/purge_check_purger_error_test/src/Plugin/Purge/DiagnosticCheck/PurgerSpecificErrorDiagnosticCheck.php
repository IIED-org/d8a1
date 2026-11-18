<?php

declare(strict_types=1);

namespace Drupal\purge_check_purger_error_test\Plugin\Purge\DiagnosticCheck;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeDiagnosticCheck;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;

/**
 * Always reports an error, but only for the 'onfire' purger.
 */
#[PurgeDiagnosticCheck(
  id: "purgererror",
  title: new TranslatableMarkup("Purger specific error"),
  description: new TranslatableMarkup("A fake test to test the diagnostics api."),
  dependent_queue_plugins: [],
  dependent_purger_plugins: ["onfire"]
)]
final class PurgerSpecificErrorDiagnosticCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->recommendation = $this->t("This is a purger error for testing.");
    return self::SEVERITY_ERROR;
  }

}
