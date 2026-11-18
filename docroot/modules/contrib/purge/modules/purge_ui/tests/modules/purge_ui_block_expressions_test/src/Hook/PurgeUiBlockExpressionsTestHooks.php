<?php

declare(strict_types=1);

namespace Drupal\purge_ui_block_expressions_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Test implementation of hook_purge_ui_block_expressions_alter().
 */
final class PurgeUiBlockExpressionsTestHooks {

  public function __construct(
    #[Autowire(service: 'state')]
    protected readonly StateInterface $state,
  ) {}

  /**
   * Implements hook_purge_ui_block_expressions_alter().
   */
  #[Hook('purge_ui_block_expressions_alter')]
  public function purgeUiBlockExpressionsAlter(array &$expressions, array $config): void {
    $this->state->set('purge_ui_block_expressions_test.config', $config);
    $this->state->set('purge_ui_block_expressions_test.expressions', $expressions);
    foreach ($this->state->get('purge_ui_block_expressions_test.add', []) as $expression) {
      $expressions[] = $expression;
    }
    if ($this->state->get('purge_ui_block_expressions_test.clear', FALSE)) {
      $expressions = [];
    }
  }

}
