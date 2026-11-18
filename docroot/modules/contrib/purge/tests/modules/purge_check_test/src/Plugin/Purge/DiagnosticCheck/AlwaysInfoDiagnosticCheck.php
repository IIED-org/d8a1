<?php

namespace Drupal\purge_check_test\Plugin\Purge\DiagnosticCheck;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\purge\Attribute\PurgeDiagnosticCheck;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;

/**
 * Checks if there is a purger plugin that invalidates an external cache.
 */
#[PurgeDiagnosticCheck(
  id: "alwaysinfo",
  title: new TranslatableMarkup("Always informational"),
  description: new TranslatableMarkup("A fake test to test the diagnostics api."),
  dependent_queue_plugins: [],
  dependent_purger_plugins: []
)]
class AlwaysInfoDiagnosticCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->recommendation = $this->t("This is info for testing.");
    return self::SEVERITY_INFO;
  }

}
