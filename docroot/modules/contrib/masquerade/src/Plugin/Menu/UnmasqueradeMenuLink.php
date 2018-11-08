<?php

namespace Drupal\masquerade\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\masquerade\Masquerade;

/**
 * A menu link that shows "Unmasquerade".
 */
class UnmasqueradeMenuLink extends MenuLinkDefault {

  /**
   * The masquerade status.
   *
   * @var bool
   */
  protected $is_masquerading;

  /**
   * Constructs a new UnmasqueradeMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_override
   *   The static override storage.
   * @param \Drupal\masquerade\Masquerade $masquerade
   *   The masquerade status.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, \Drupal\masquerade\Masquerade $masquerade) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);

    $this->is_masquerading = $masquerade;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $status = \Drupal::service('cache_context.session.is_masquerading')->getContext();
    if (!empty($status)) {
      $plugin_definition['enabled'] = TRUE;
    }
    else {
      $plugin_definition['enabled'] = FALSE;
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('masquerade')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Unmasquerade');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Switch back to your user account.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['session.is_masquerading'];
  }

}
