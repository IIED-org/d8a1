<?php

namespace Drupal\Tests\purge\Kernel\Invalidation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class TagInvalidationTest extends PluginTestBase {

  /**
   * The plugin ID of the invalidation type being tested.
   *
   * @var string
   */
  protected $pluginId = 'tag';

  /**
   * String expressions valid to the invalidation type being tested.
   *
   * @var null|mixed[]
   */
  protected $expressions = [
    'tag',
    'user:1',
    'menu:footer',
  ];

  /**
   * String expressions invalid to the invalidation type being tested.
   *
   * @var null|mixed[]
   */
  protected $expressionsInvalid = [
    NULL,
    '',
    ['node', '1'],
    'wildtag:*',
    'tag with space',
    'trailing:space ',
    "tag\twith:tab",
    "tag\nwith:newline",
  ];

}
