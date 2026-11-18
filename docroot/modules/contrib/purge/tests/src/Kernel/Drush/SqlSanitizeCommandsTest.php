<?php

declare(strict_types=1);

namespace Drupal\Tests\purge\Kernel\Drush;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\purge\Drush\Commands\SqlSanitizeCommands;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Tests \Drupal\purge\Drush\Commands\SqlSanitizeCommands.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
final class SqlSanitizeCommandsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'purge_queuer_test',
    'purge_purger_test',
  ];

  /**
   * The sanitize plugin under test.
   *
   * @var \Drupal\purge\Drush\Commands\SqlSanitizeCommands
   */
  protected $command;

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    if (!class_exists(DrushCommands::class)) {
      $this->markTestSkipped('Drush is not installed.');
    }
    // Drush loads its procedural helpers such as dt() during preflight, which
    // never happens inside PHPUnit.
    if (!function_exists('dt')) {
      $drush_root = dirname((new \ReflectionClass(Drush::class))->getFileName(), 2);
      require_once $drush_root . '/includes/output.inc';
    }
    $this->initializeQueueService();
    $this->initializeQueuersService();
    $this->purgeQueue->emptyQueue();

    $this->command = SqlSanitizeCommands::create($this->container);
    $this->command->setInput(new ArrayInput([]));
    $this->command->setOutput(new NullOutput());
  }

  /**
   * Tests that the plugin announces itself before the confirmation prompt.
   */
  public function testMessages(): void {
    $messages = ['Truncate sessions table.'];
    $this->command->messages($messages, new ArrayInput([]));
    $this->assertCount(2, $messages);
    $this->assertSame('Empty the purge queue.', $messages[1]);
  }

  /**
   * Tests that the plugin empties the queue when sql:sanitize runs.
   */
  public function testSanitize(): void {
    $this->purgeQueue->add($this->purgeQueuers->get('a'), $this->getInvalidations(5));
    $this->assertSame(5, $this->purgeQueue->numberOfItems());

    $command_data = new CommandData(new AnnotationData(), new ArrayInput([]), new NullOutput());
    $this->command->sanitize(0, $command_data);

    $this->assertSame(0, $this->purgeQueue->numberOfItems());
    $this->assertEmpty($this->purgeQueue->claim(10, 10));
  }

}
