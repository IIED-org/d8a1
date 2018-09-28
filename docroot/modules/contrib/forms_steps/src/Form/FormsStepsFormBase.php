<?php

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\forms_steps\FormsStepsEvent;
use Drupal\forms_steps\Step;

/**
 * Class FormsStepsFormBase
 *
 * @package Drupal\forms_steps\Form
 */
class FormsStepsFormBase extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => '',
      '#description' => $this->t('Label for the step.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];

    // TODO: remove 'node.' concatenation with step node type.
    $form['target_form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#maxlength' => 255,
      '#default_value' => 'default',
      '#description' => $this->t('Form mode of the Content type.'),
      '#required' => TRUE,
      '#options' => Step::formModes(),
    ];

    $form['target_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#maxlength' => 255,
      '#description' => $this->t('Content type of the form.'),
      '#required' => TRUE,
      '#options' => node_type_get_names(),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Step URL'),
      '#size' => 30,
      '#description' => $this->t('Form step URL.'),
      '#placeholder' => '/my_form/step1',
      '#required' => TRUE,
    ];

    return $form;
  }

}
