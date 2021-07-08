<?php

namespace Drupal\Tests\paragraphs\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\paragraphs_test\Form\TestEmbeddedEntityForm;

/**
 * Tests the langcode change mechanics of paragraphs.
 *
 * @group paragraphs
 */
class ParagraphsLangcodeChangeTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'entity_test',
    'paragraphs',
    'paragraphs_test',
    'entity_reference_revisions',
    'node',
    'language',
    'file',
  ];

  /**
   * The machine name of the node type.
   *
   * @var string
   */
  protected $nodeType;

  /**
   * The machine name of the node's paragraphs field.
   *
   * @var string
   */
  protected $nodeParagraphsfieldName;

  /**
   * The machine name of the paragraph type.
   *
   * @var string
   */
  protected $paragraphType;

  /**
   * The current node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The current paragraph.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * The array of the current form.
   *
   * @var array
   */
  protected $form;

  /**
   * The current form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * The current form object.
   *
   * @var \Drupal\Core\Entity\ContentEntityFormInterface
   */
  protected $formObject;

  /**
   * The current entity form display.
   *
   * @var \Drupal\Core\Entity\Entity\EntityFormDisplay
   */
  protected $formDisplay;

  /**
   * The current form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');

    $this->installSchema('system', ['key_value_expire']);
    $this->installSchema('node', ['node_access']);

    $this->installConfig(static::$modules);

    $this->formBuilder = $this->container->get('form_builder');

    // Activate Spanish language, so there are two languages activated.
    $this->entityTypeManager->getStorage('configurable_language')->create([
      'id' => 'es',
    ])->save();

    // Create a paragraph type.
    $this->paragraphType = $this->randomMachineName();
    $this->entityTypeManager->getStorage('paragraphs_type')->create([
      'label' => $this->randomString(),
      'id' => $this->paragraphType,
      'status' => TRUE,
    ])->save();

    // Create a node type.
    $this->nodeType = $this->randomMachineName();
    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => $this->randomString(),
      'type' => $this->nodeType,
      'create_body' => FALSE,
    ])->save();

    // Enable translations on the node type and paragraph type.
    ContentLanguageSettings::loadByEntityTypeBundle('node', $this->nodeType)
      ->setLanguageAlterable(TRUE)
      ->setDefaultLangcode('en')
      ->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', $this->paragraphType)
      ->setLanguageAlterable(TRUE)
      ->setDefaultLangcode('en')
      ->save();

    // Create a field with paragraphs for the node type.
    $this->nodeParagraphsfieldName = 'field_paragraphs';
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'field_name' => $this->nodeParagraphsfieldName,
      'settings' => [
        'target_type' => 'paragraph',
      ],
      'cardinality' => -1,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => $this->nodeType,
      'field_name' => $this->nodeParagraphsfieldName,
      'label' => $this->randomString(),
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => 1,
          'target_bundles' => NULL,
          'target_bundles_drag_drop' => [
            $this->paragraphType => [
              'weight' => 0,
              'enabled' => FALSE,
            ],
          ],
        ],
      ],
    ])->save();

    // Create the form display of the node type,
    // with the language switcher enabled.
    // The default autocomplete widget does not work properly
    // within a kernel test. Thus, use a simple select list widget instead.
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $this->nodeType,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('langcode', [
      'type' => 'language_select',
      'region' => 'content',
      'weight' => 10,
    ])->setComponent('uid', [
      'type' => 'options_select',
      'region' => 'content',
      'weight' => 100,
    ])->save();

    $this->formDisplay = EntityFormDisplay::load('node.' . $this->nodeType . '.default');

    $this->createUser(['uid' => 1, 'name' => 'user1'])->save();

    $this->paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => $this->paragraphType,
    ]);

    $this->node = $this->entityTypeManager->getStorage('node')->create([
      'type' => $this->nodeType,
      'title' => $this->randomString(),
      'status' => TRUE,
      'uid' => 1,
      'langcode' => 'es',
      $this->nodeParagraphsfieldName => [$this->paragraph],
    ]);
  }

  /**
   * Tests the langcode change within a node form having a classic widget.
   */
  public function testChangeWithClassicWidget() {
    $this->doTestLangcodeChange(
      [
        'type' => 'entity_reference_paragraphs',
        'weight' => 5,
      ],
      FALSE
    );
  }

  /**
   * Tests the langcode change within a node form having an experimental widget.
   */
  public function testChangeWithExperimentalWidget() {
    $this->doTestLangcodeChange(
      [
        'type' => 'paragraphs',
        'weight' => 15,
      ],
      FALSE
    );
  }

  /**
   * Tests langcode change within an embedded node form and classic widget.
   */
  public function testChangeWithEmbeddedClassicWidget() {
    $this->doTestLangcodeChange(
      [
        'type' => 'entity_reference_paragraphs',
        'weight' => 5,
      ],
      TRUE
    );
  }

  /**
   * Tests langcode change within an embedded node form and experimental widget.
   */
  public function testChangeWithEmbeddedExperimentalWidget() {
    $this->doTestLangcodeChange(
      [
        'type' => 'paragraphs',
        'weight' => 15,
      ],
      TRUE
    );
  }

  /**
   * Performs the test run with the given options.
   *
   * @param array $widget_options
   *   The paragraph widget options.
   * @param bool $embedded
   *   (Optional) Whether the embedded form should be used or not.
   */
  protected function doTestLangcodeChange(array $widget_options, $embedded = FALSE) {
    $this->formDisplay
      ->setComponent($this->nodeParagraphsfieldName, $widget_options)
      ->save();
    $this->doTestChangeWithinNodeForm($embedded);
  }

  /**
   * Performs the test run regards the node form.
   *
   * @param bool $embedded
   *   (Optional) Whether the embedded form should be used or not.
   */
  protected function doTestChangeWithinNodeForm($embedded = FALSE) {
    $this->assertEquals('es', $this->node->language()->getId(), "The node was created with langcode es.");
    $this->assertEquals('en', $this->paragraph->language()->getId(), "The paragraph was created with its default langcode en.");

    // Use this form to add a node.
    $this->buildNodeForm($embedded);

    $this->submitNodeForm();

    $langcode = $this->node->language()->getId();
    $this->assertEquals('es', $langcode, "The node's langcode remains unchanged to value es (after submission).");
    $this->assertEquals($langcode, $this->paragraph->language()->getId(), "The paragraph's langcode was inherited from its parent (after submission).");

    // Switch to the form again.
    $this->buildNodeForm($embedded);

    // Change the node's language from es to en.
    if ($embedded) {
      $this->formState->setValue(['embedded_entity_form', 'langcode'], [['value' => 'en']]);
    }
    else {
      $this->formState->setValue('langcode', [['value' => 'en']]);
    }
    $this->submitNodeForm();

    $langcode = $this->node->language()->getId();
    $this->assertEquals('en', $langcode, "The node's langcode was updated to value en (after submission).");
    $this->assertEquals($langcode, $this->paragraph->language()->getId(), "The paragraph's updated langcode was inherited from its parent (after submission).");

    // Rebuild the form once more and make sure
    // that the langcode change does not get lost.
    $this->buildNodeForm($embedded);

    // Change the node's language from en to es.
    if ($embedded) {
      $this->formState->setValue(['embedded_entity_form', 'langcode'], [['value' => 'es']]);
    }
    else {
      $this->formState->setValue('langcode', [['value' => 'es']]);
    }

    $this->submitNodeForm();

    $langcode = $this->node->language()->getId();
    $this->assertEquals('es', $langcode, "The node's langcode was set to es (after rebuild and submission).");
    $this->assertEquals($langcode, $this->paragraph->language()->getId(), "The paragraph's langcode was inherited from its parent (after rebuild and submission).");
  }

  /**
   * Builds the node form.
   *
   * @param bool $embedded
   *   (Optional) Whether the embedded form should be used or not.
   */
  protected function buildNodeForm($embedded = FALSE) {
    if ($embedded) {
      $this->formObject = new TestEmbeddedEntityForm($this->node);
    }
    else {
      $this->formObject = $this->entityTypeManager->getFormObject('node', 'default');
      $this->formObject->setEntity($this->node);
    }
    $this->formState = (new FormState())
      ->disableRedirect()
      ->setFormObject($this->formObject);
    $this->form = $this->formBuilder->buildForm($this->formObject, $this->formState);
    $this->reassignEntities();
  }

  /**
   * Submits the node form, emulating the save operation as triggering element.
   */
  protected function submitNodeForm() {
    // Submit the form with the default save operation.
    $this->formState->setValue('op', $this->formState->getValue('submit'));
    $this->formBuilder->submitForm($this->formObject, $this->formState);
    $this->reassignEntities();
  }

  /**
   * Helper method to reassign the current entity objects.
   */
  protected function reassignEntities() {
    $this->node = $this->formObject->getEntity();
    $paragraphs = $this->node->get($this->nodeParagraphsfieldName)->referencedEntities();
    $this->paragraph = reset($paragraphs);
  }

  /**
   * {@inheritdoc}
   */
  protected function randomMachineName($length = 8) {
    return mb_strtolower(parent::randomMachineName());
  }

}
