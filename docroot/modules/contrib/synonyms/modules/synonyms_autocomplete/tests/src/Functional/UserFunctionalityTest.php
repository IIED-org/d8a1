<?php

namespace Drupal\Tests\synonyms_autocomplete\Functional;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\BrowserTestBase;
use Drupal\synonyms\Entity\Synonym;

/**
 * Checks if user functionality works correctly.
 *
 * @group synonyms
 */
class UserFunctionalityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'synonyms',
    'synonyms_autocomplete',
  ];

  /**
   * Use the full install profile so that there are data structures to use.
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A list of terms to test with.
   *
   * @var array
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->container->get('router.builder')->rebuild();

    // Log in the root user.
    $this->drupalLogin($this->rootUser);

    // Add a synonyms field to the Tags vocabulary.
    // @todo Recreate these steps using the field APIs.
    $this->drupalGet('admin/structure/taxonomy/manage/tags/overview/fields/add-field');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'new_storage_type' => 'string',
      'label' => 'Synonyms',
      'field_name' => 'synonyms',
    ];
    $this->submitForm($edit, 'Save and continue');
    $this->assertSession()->pageTextContains('Your settings have been saved.');
    $edit = [
      'cardinality' => -1,
    ];
    $this->submitForm($edit, 'Save field settings');
    $this->assertSession()->pageTextContains('Updated field Synonyms field settings.');
    $this->submitForm([], 'Save settings');
    $this->assertSession()->pageTextContains('Saved Synonyms configuration.');

    // Configure the Tags field on the Article content type.
    $this->drupalGet('admin/structure/types/manage/article/form-display');
    $edit = [
      'fields[field_tags][type]' => 'synonyms_autocomplete',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');

    // Configure the Synonyms module.
    \Drupal::configFactory()
      ->getEditable('synonyms_autocomplete.behavior.taxonomy_term.tags')
      ->set('status', TRUE)
      ->set('wording', NULL)
      ->save(TRUE);

    $synonym = Synonym::create([
      'id' => 'field.taxonomy_term.tags.field_synonyms',
      'langcode' => 'en',
      'status' => TRUE,
    ]);
    $synonym->setProviderPlugin('field:taxonomy_term.tags.field_synonyms');
    $synonym->save();

    // Create some fillter terms.
    for ($ctr = 1; $ctr <= 5; $ctr++) {
      $this->terms[] = Term::create([
        'vid' => 'tag',
        'name' => $this->randomString(8),
      ])->save();
    }
  }

  /**
   * Make sure the autocomplete field works properly.
   *
   * This is testing against an autocomplete tag field with auto-creation.
   */
  public function testAutocompleteField() {
    // Submitting the node form with a term string that doesn't exist.
    $this->drupalGet('node/add/article');
    $title = $this->randomString(8) . ' ' . $this->randomString(8);
    $test_string = 'Something';
    $edit = [
      'title[0][value]' => $title,
      'field_tags' => $test_string,
    ];
    $this->submitForm($edit, 'Save');

    // It should not have thrown a HTTP error, i.e. it should do a regular page
    // load and not throw a PHP error / HTTP status 500.
    $this->assertSession()->statusCodeEquals(200);

    // The page should not include a message indicating that the node was
    // created successfully.
    $this->assertSession()->pageTextNotContains('Article ' . $title . ' has been created.');

    // Confirm the error message is visible.
    $this->assertSession()->pageTextContains('At least one of the items entered could not be found.');

    // Make sure the values originally entered were retained.
    foreach ($edit as $field_name => $field_value) {
      $this->assertSession()->fieldExists($field_name);
      $this->assertSession()->fieldValueEquals($field_name, $field_value);
    }
  }

}
