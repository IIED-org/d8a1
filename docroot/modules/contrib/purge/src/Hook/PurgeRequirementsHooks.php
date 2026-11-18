<?php

declare(strict_types=1);

namespace Drupal\purge\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticsServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Requirements hook implementations for the purge module.
 *
 * Drupal 11.3 and later skip purge_requirements() because it carries
 * #[LegacyRequirementsHook]; this class reports the same rows there. Drupal 10
 * and 11.0/11.1 keep using purge_requirements(), which stays in purge.install.
 */
final class PurgeRequirementsHooks {

  public function __construct(
    #[Autowire(service: 'purge.diagnostics')]
    protected readonly DiagnosticsServiceInterface $purgeDiagnostics,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    return self::requirements($this->purgeDiagnostics);
  }

  /**
   * Builds the status report rows for diagnostic warnings and errors.
   *
   * Shared by this hook class and the legacy purge_requirements().
   *
   * @param \Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticsServiceInterface $diagnostics
   *   The purge diagnostics service.
   *
   * @return array
   *   Requirement rows as hook_runtime_requirements() expects them.
   */
  public static function requirements(DiagnosticsServiceInterface $diagnostics): array {
    return $diagnostics->toRequirementsArray($diagnostics->filterWarningAndErrors(), TRUE);
  }

}
