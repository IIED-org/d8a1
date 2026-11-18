<?php

declare(strict_types=1);

namespace Drupal\Tests\purge_ui\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\purge_ui\Form\PurgeBlockForm;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests \Drupal\purge_ui\Form\PurgeBlockForm.
 *
 * @group purge_ui
 */
#[Group('purge_ui')]
#[RunTestsInSeparateProcesses]
final class PurgeBlockFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'purge',
    'purge_ui',
    'purge_purger_test',
    'purge_ui_block_expressions_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    $this->initializePurgersService(['good']);
    $this->initializeQueuersService(['purge_ui_block_queuer']);
    $this->initializeQueueService();
    $this->initializeInvalidationFactoryService();
    $request = Request::create('http://localhost/node/1?foo=bar');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Builds the block form and returns the gathered expressions.
   *
   * @param string $type
   *   The invalidation type the block is configured for.
   * @param string $execution
   *   The execution mode the block is configured for.
   *
   * @return array
   *   The 'expressions' build info entry, as the form derived it.
   */
  protected function buildExpressions(string $type, string $execution = 'queue'): array {
    $config = [
      'purge_block_id' => 'test',
      'description' => '',
      'submit_label' => 'Purge',
      'type' => $type,
      'execution' => $execution,
    ];
    $form = PurgeBlockForm::create($this->container, $config);
    $form_state = new FormState();
    $this->container->get('form_builder')->buildForm($form, $form_state);
    return $form_state->getBuildInfo()['expressions'];
  }

  /**
   * Tests the expressions derived from the request and passed to the hook.
   */
  public function testHookReceivesRequestExpressionsAndConfig(): void {
    $state = $this->container->get('state');

    $expressions = $this->buildExpressions('url');
    $this->assertSame([
      'http://localhost/node/1?foo=bar',
      'http://localhost/node/1',
    ], $expressions);
    $this->assertSame($expressions, $state->get('purge_ui_block_expressions_test.expressions'));
    $config = $state->get('purge_ui_block_expressions_test.config');
    $this->assertSame('url', $config['type']);
    $this->assertSame('queue', $config['execution']);
    $this->assertSame('test', $config['purge_block_id']);

    $this->assertSame(['node/1?foo=bar', 'node/1'], $this->buildExpressions('path'));
    $this->assertSame('path', $state->get('purge_ui_block_expressions_test.config')['type']);

    $this->assertSame([NULL], $this->buildExpressions('everything'));
    $this->assertSame('everything', $state->get('purge_ui_block_expressions_test.config')['type']);
  }

  /**
   * Tests that hook implementations can add and remove expressions.
   */
  public function testHookAltersExpressions(): void {
    $state = $this->container->get('state');

    // Added expressions are kept, and duplicates are removed.
    $state->set('purge_ui_block_expressions_test.add', [
      'http://localhost/old-alias',
      'http://localhost/node/1',
    ]);
    $this->assertSame([
      'http://localhost/node/1?foo=bar',
      'http://localhost/node/1',
      'http://localhost/old-alias',
    ], $this->buildExpressions('url'));

    // Removing every expression makes the form refuse to submit.
    $state->set('purge_ui_block_expressions_test.clear', TRUE);
    $this->assertSame([], $this->buildExpressions('url'));
  }

  /**
   * Tests that added expressions end up in the queue on submission.
   */
  public function testAddedExpressionsAreQueued(): void {
    $this->container->get('state')->set('purge_ui_block_expressions_test.add', [
      'http://localhost/old-alias',
    ]);
    $config = [
      'purge_block_id' => 'test',
      'description' => '',
      'submit_label' => 'Purge',
      'type' => 'url',
      'execution' => 'queue',
    ];
    $form = PurgeBlockForm::create($this->container, $config);
    $form_state = new FormState();
    $form_state->setValue('op', 'Purge');
    $this->container->get('form_builder')->submitForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $this->assertSame(3, $this->purgeQueue->numberOfItems());
    $expressions = [];
    foreach ($this->purgeQueue->claim(10) as $invalidation) {
      $this->assertSame('url', $invalidation->getType());
      $expressions[] = $invalidation->getExpression();
    }
    sort($expressions);
    $this->assertSame([
      'http://localhost/node/1',
      'http://localhost/node/1?foo=bar',
      'http://localhost/old-alias',
    ], $expressions);
  }

}
