<?php

namespace Drupal\Tests\form_mode_manager\Functional;

use Drupal\node\Entity\Node;

/**
 * Tests the Form mode manager content translation.
 *
 * @group form_mode_manager
 */
class FormModeManagerContentTranslationTest extends FormModeManagerBase {

  /**
   * Stores the form mode manager node content used by this test.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $fmmNode;

  /**
   * Stores the default node content used by this test.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $articleNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Generate content for this test.
    $this->fmmNode = $this->createNode(['type' => $this->nodeTypeFmm1->id()]);
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->articleNode = $this->createNode(['type' => 'article']);
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test content translation for FMM works.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testContentTranslationFormModeManager() {
    // Add language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');

    // Enable content translation on articles and FMM node type.
    $this->drupalGet('admin/config/regional/content-language');
    $edit = [
      'entity_types[node]'                                                                        => TRUE,
      'settings[node][article][translatable]'                                                     => TRUE,
      'settings[node][article][settings][language][language_alterable]'                           => TRUE,
      'settings[node][' . $this->nodeTypeFmm1->id() . '][translatable]'                           => TRUE,
      'settings[node][' . $this->nodeTypeFmm1->id() . '][settings][language][language_alterable]' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Create a translation with a default, non-FMM node.
    $this->drupalGet('fr/node/' . $this->articleNode->id() . '/translations/add/en/fr');
    $this->submitForm([], 'Save (this translation)');
    $this->articleNode = Node::load($this->articleNode->id());
    $this->assertEquals(TRUE, $this->articleNode->hasTranslation('fr'));

    // Create a translation with default form mode for FMM node.
    $this->drupalGet('fr/node/' . $this->fmmNode->id() . '/translations/add/en/fr');
    $this->submitForm([], 'Save (this translation)');
    $this->fmmNode = Node::load($this->fmmNode->id());
    $this->assertEquals(TRUE, $this->fmmNode->hasTranslation('fr'));

    // Delete the translation.
    $this->fmmNode->removeTranslation('fr');
    $this->fmmNode->save();
    $this->assertEquals(FALSE, $this->fmmNode->hasTranslation('fr'));

    // Create a translation with FMM form mode for FMM node.
    $node_form_mode_id = $this->formModeManager->getFormModeMachineName($this->nodeFormMode->id());
    $this->drupalGet('fr/node/' . $this->fmmNode->id() . '/translations/add/' . $node_form_mode_id . '/en/fr');
    $this->submitForm([], 'Save (this translation)');
    $this->fmmNode = Node::load($this->fmmNode->id());
    $this->assertEquals(TRUE, $this->fmmNode->hasTranslation('fr'));
  }

}
