<?php

declare(strict_types=1);

namespace Drupal\purge\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form hook implementations for the purge module.
 */
final class PurgeFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for system_performance_settings.
   *
   * Add some serious options to the page_cache_maximum_age setting.
   */
  #[Hook('form_system_performance_settings_alter')]
  public function formSystemPerformanceSettingsAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['caching']['page_cache_maximum_age']['#options'][604800] = $this->t("1 week");
    $form['caching']['page_cache_maximum_age']['#options'][2764800] = $this->t("1 month");
    $form['caching']['page_cache_maximum_age']['#options'][16588800] = $this->t("6 months");
    $form['caching']['page_cache_maximum_age']['#options'][31536000] = $this->t("1 year (recommended for external cache invalidation)");
  }

}
