<?php

declare(strict_types=1);

namespace Drupal\Tests\purge_ui\Kernel;

use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that purge_ui exposes its dashboard in the administration menu.
 *
 * @group purge_ui
 */
#[Group('purge_ui')]
#[RunTestsInSeparateProcesses]
final class MenuLinkTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'purge', 'purge_ui'];

  /**
   * Tests that the dashboard menu link is discovered and placed correctly.
   */
  public function testDashboardMenuLink(): void {
    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.menu.link');
    // Discovered definitions only become loadable once the tree storage has
    // been populated, which happens on rebuild.
    $manager->rebuild();
    $this->assertTrue($manager->hasDefinition('purge_ui.dashboard'));
    $definition = $manager->getDefinition('purge_ui.dashboard');
    $this->assertSame('purge_ui.dashboard', $definition['route_name']);
    $this->assertSame('admin', $definition['menu_name']);
    $this->assertSame('system.performance_settings', $definition['parent']);
    $this->assertSame('Purge', (string) $definition['title']);
  }

}
