<?php

namespace Drupal\Tests\forms_steps\Traits;

use Drupal\user\Entity\Role;

trait FormsStepsTestTrait
{

  /** Data used to build the test. */
  private $data;

  /**
   * A simple user.
   *
   * @var object
   */
  private $user;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function formsStepsSetup()
  {
    $permissions = [
      'administer forms_steps',
      'administer content types',
      'administer nodes',
      'administer forms_steps',
      'administer display modes',
      'administer node fields',
      'administer node display',
      'administer node form display',
    ];

    $this->data = [
      'form_display_modes' => [
        1 => [
          'label' => 'Test Form Mode',
          'id' => 'test_form_mode',
        ],
      ],
      'forms_steps' => [
        'label' => 'Test Form Step',
        'id' => 'test_form_step',
        'description' => 'Test Form Step description',
        'steps' => [
          1 => [
            'label' => 'Add Test Step 1',
            'id' => 'add_test_step_article',
            'target_form_mode' => 'default',
            'target_node_type' => 'article',
            'url' => '/my_test_form/step_1',
            'previous' => NULL,
          ],
          2 => [
            'label' => 'Edit Test Step 2',
            'id' => 'edit_test_step_article',
            'target_form_mode' => 'node.test_form_mode',
            'target_node_type' => 'article',
            'url' => '/my_test_form/step_2',
            'previous' => 'Previous',
          ],
          3 => [
            'label' => 'Add Test Step 3',
            'id' => 'add_test_step_page',
            'target_form_mode' => 'default',
            'target_node_type' => 'page',
            'url' => '/my_test_form/step_3',
            'previous' => 'Previous',
          ],
          4 => [
            'label' => 'Edit Test Step 4',
            'id' => 'edit_test_step_article_bis',
            'target_form_mode' => 'node.test_form_mode',
            'target_node_type' => 'article',
            'url' => '/my_test_form/step_4',
            'previous' => 'Previous',
          ],
        ],
      ],
    ];

    $this->checkPermissions($permissions);
    $this->user = $this->drupalCreateUser($permissions);

    // Login.
    $this->drupalLogin($this->user);
  }

  public function formsModesCreation() {

    // Creation of the page node.
    $this->createContentType(
      [
        'type' => 'page',
      ]
    );

    // Creation of all form display modes.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      // Access form mode add page.
      $this->drupalGet('admin/structure/display-modes/form/add/node');

      // Add a form mode.
      $this->drupalPostForm(
        null,
        [
          'label' => $form_display_mode['label'],
          'id' => $form_display_mode['id'],
        ],
        t('Save')
      );

      Role::load($this->user->getRoles()[1])
        ->grantPermission('use node.'.$form_display_mode['id'].' form mode')
        ->save();
    }

    // Create Article content type.
    $this->createContentType(
      [
        'type' => 'article',
      ]
    );

    Role::load($this->user->getRoles()[1])
      ->grantPermission("administer nodes")
      ->save();

    // Access article's form display page.
    $this->drupalGet('admin/structure/types/manage/article/form-display');

    // Activate Test Form Modes as a custom display mode.
    foreach ($this->data['form_display_modes'] as $form_display_mode) {
      $this->drupalPostForm(
        null,
        [
          "display_modes_custom[${form_display_mode['id']}]" => $form_display_mode['id'],
        ],
        t('Save')
      );
    }

    // Configure the visible fields.
    $this->drupalGet(
      "admin/structure/types/manage/article/form-display/${form_display_mode['id']}"
    );

    $this->drupalPostForm(
      null,
      [
        'fields[title][region]' => 'content',
        'fields[body][region]' => 'hidden',
        'fields[status][region]' => 'hidden',
        'fields[uid][region]' => 'hidden',
        'fields[created][region]' => 'hidden',
        'fields[promote][region]' => 'hidden',
        'fields[sticky][region]' => 'hidden',
      ],
      t('Save')
    );

    // Access forms steps add page.
    $this->drupalGet('admin/config/workflow/forms_steps/add');

    // Test the creation of a form step.
    $this->drupalPostForm(
      null,
      [
        'label' => $this->data['forms_steps']['label'],
        'id' => $this->data['forms_steps']['id'],
        'description' => $this->data['forms_steps']['description'],
      ],
      t('Save')
    );

    // Perform steps creation.
    foreach ($this->data['forms_steps']['steps'] as $step) {
      // Access step add page of the form step.
      $this->drupalGet(
        "admin/config/workflow/forms_steps/{$this->data['forms_steps']['id']}/add_step"
      );

      // Test the creation of an add step.
      $this->drupalPostForm(
        null,
        [
          'label' => $step['label'],
          'id' => $step['id'],
          'target_form_mode' => $step['target_form_mode'],
          'target_node_type' => $step['target_node_type'],
          'url' => $step['url'],
        ],
        t('Save')
      );

      if (!is_null($step['previous'])) {
        // Update step with previous label
        $this->drupalPostForm(
          'admin/config/workflow/forms_steps/test_form_step/step/'.$step['id'],
          [
            'display_previous' => TRUE,
            'previous_label' => $step['previous'],
          ],
          t('Save')
        );
      }
    }
  }
}
