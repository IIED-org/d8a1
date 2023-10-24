<?php

namespace Drupal\synonyms_select\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\synonyms\ProviderInterface\FormatWordingTrait;

/**
 * Synonyms select widget settings form.
 */
class SettingsForm extends ConfigFormBase {

  use StringTranslationTrait, FormatWordingTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'synonyms_select_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['synonyms_select.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $description = $this->t('Specify the wording with which the select widget suggestions should be presented. Available replacement tokens are: @replacements This will also serve as a fallback wording if more specific wordings are left empty.', [
      '@replacements' => $this->formatWordingAvailableTokens(),
    ]);

    // The 'Default wording' textfield.
    $form['default_wording'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default wording'),
      '#default_value' => $this->config('synonyms_select.settings')->get('default_wording'),
      '#description' => $description,
    ];

    // Option for sorting select dropdown options.
    $form['sort_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sort dropdown values'),
      '#default_value' => $this->config('synonyms_select.settings')->get('sort_select'),
      '#description' => $this->t('Sorts the select options using the natcasesort() function'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('synonyms_select.settings')
      ->set('default_wording', $form_state->getValue('default_wording'))
      ->set('sort_select', $form_state->getValue('sort_select'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
