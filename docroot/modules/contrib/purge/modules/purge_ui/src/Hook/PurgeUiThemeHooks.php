<?php

declare(strict_types=1);

namespace Drupal\purge_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for the purge_ui module.
 */
final class PurgeUiThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'purge_ui_dashboard' => [
        'render element' => 'build',
      ],
      'purge_ui_diagnostics' => [
        'variables' => [
          'diagnostics' => [],
        ],
      ],
    ];
  }

}
