<?php

namespace Drupal\purge_queuer_pathalias\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\purge_ui\Form\QueuerConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the Path alias queuer.
 */
class ConfigurationForm extends QueuerConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The 'purge.purgers' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Deliberately not a constructor override: ConfigFormBase's constructor
    // signature changed between the supported core versions.
    $instance = parent::create($container);
    $instance->purgePurgers = $container->get('purge.purgers');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['purge_queuer_pathalias.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purge_queuer_pathalias.configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('purge_queuer_pathalias.settings');
    $supported = $this->purgePurgers->getTypes();

    $unsupported = $this->t('not supported by any enabled purger');
    $options = ['auto' => $this->t('Automatic (prefers URL, falls back to path)')];
    $options['url'] = in_array('url', $supported, TRUE)
      ? $this->t('URL')
      : $this->t('URL (@unsupported)', ['@unsupported' => $unsupported]);
    $options['path'] = in_array('path', $supported, TRUE)
      ? $this->t('Path')
      : $this->t('Path (@unsupported)', ['@unsupported' => $unsupported]);

    $form['invalidation_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Invalidation type'),
      '#description' => $this->t('Which kind of invalidation gets queued for a changed alias.'),
      '#options' => $options,
      '#default_value' => $config->get('invalidation_type') ?: 'auto',
    ];
    if (!array_intersect(['url', 'path'], $supported)) {
      $form['unsupported'] = [
        '#type' => 'markup',
        '#markup' => '<p><b>' . $this->t('None of your enabled purgers support URL or path invalidation, so nothing will be queued. Install a purger that does before relying on this queuer.') . '</b></p>',
        '#weight' => -10,
      ];
    }

    $base_urls = $config->get('base_urls');
    $form['base_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Base URLs'),
      '#description' => $this->t('One absolute base URL per line, for example <code>https://www.example.com</code>. Every alias gets invalidated on <em>each</em> of these, which is what multi-domain sites need. Set this when your site answers on more than one host, and when aliases get saved from the command line - drush, cron, migrations - where Drupal cannot determine the URL of your site. When left empty, the domains of language negotiation are used if configured, and otherwise the host of the request that saved the alias.'),
      '#default_value' => is_array($base_urls) ? implode("\n", $base_urls) : '',
      '#rows' => 3,
    ];

    $form['events'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Queue on'),
    ];
    $form['events']['queue_insert'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Alias creation'),
      '#description' => $this->t('Purges the 404 that your CDN cached before the alias existed.'),
      '#default_value' => $config->get('queue_insert'),
    ];
    $form['events']['queue_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Alias change'),
      '#description' => $this->t('Purges both the new alias and the old one, as the old URL now responds with a 404.'),
      '#default_value' => $config->get('queue_update'),
    ];
    $form['events']['queue_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Alias deletion'),
      '#description' => $this->t('Purges the URL that just started responding with a 404, preventing your CDN from serving a stale page.'),
      '#default_value' => $config->get('queue_delete'),
    ];

    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced'),
    ];
    $form['advanced']['expand_undefined_langcode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expand language neutral aliases'),
      '#description' => $this->t('Language neutral aliases resolve under every language, so queue every language-prefixed variant of the URL as well.'),
      '#default_value' => $config->get('expand_undefined_langcode'),
    ];
    // Which languages that expansion covers is only a meaningful question - and
    // only a real volume concern - once the site actually has languages.
    if ($this->languageManager->isMultilingual()) {
      $languages = [];
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        $languages[$langcode] = $language->getName();
      }
      $form['advanced']['expand_langcodes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Languages to expand into'),
        '#description' => $this->t('Restrict the expansion to the languages that your CDN actually serves. Leave everything unchecked to use all languages on the site. Regardless of this setting, no more than the maximum below gets queued per request.'),
        '#options' => $languages,
        '#default_value' => array_values(array_intersect(
          array_map('strval', (array) $config->get('expand_langcodes')),
          array_keys($languages)
        )),
        '#states' => [
          'visible' => [
            ':input[name="expand_undefined_langcode"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    $form['advanced']['skip_syncing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip migrations and imports'),
      '#description' => $this->t('Do not queue aliases that are created by migrations, content staging or configuration imports.'),
      '#default_value' => $config->get('skip_syncing'),
    ];
    $form['advanced']['max_per_request'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum invalidations per request'),
      '#description' => $this->t('Protects your queue against bulk alias operations. Use 0 for no limit.'),
      '#min' => 0,
      '#default_value' => (int) $config->get('max_per_request'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // The select only offers supported values, but this form is also submitted
    // programmatically - by tests and by deployment code - where element level
    // options validation does not apply.
    $type = (string) $form_state->getValue('invalidation_type');
    if (!in_array($type, ['auto', 'url', 'path'], TRUE)) {
      $form_state->setErrorByName('invalidation_type', $this->t('Select one of the offered invalidation types.'));
    }
    elseif (($type !== 'auto') && !in_array($type, $this->purgePurgers->getTypes(), TRUE)) {
      // Not an error: purgers get added after this queuer gets configured, and
      // blocking the save would leave the site unable to prepare for that.
      $this->messenger()->addWarning($this->t('None of your enabled purgers support @type invalidation, so nothing gets queued until one does.', [
        '@type' => $type,
      ]));
    }

    $base_urls = [];
    $invalid = [];
    foreach (preg_split('/\R/', (string) $form_state->getValue('base_urls')) as $base_url) {
      if (($base_url = rtrim(trim($base_url), '/')) === '') {
        continue;
      }
      $scheme = parse_url($base_url, PHP_URL_SCHEME);
      if (!in_array($scheme, ['http', 'https'], TRUE) || !UrlHelper::isValid($base_url, TRUE)) {
        $invalid[] = $base_url;
      }
      elseif (!in_array($base_url, $base_urls, TRUE)) {
        $base_urls[] = $base_url;
      }
    }
    $form_state->setValue('base_urls', $base_urls);
    if (count($invalid)) {
      $form_state->setErrorByName('base_urls', $this->t('Enter one valid absolute URL per line, for instance https://www.example.com. Invalid: @invalid', [
        '@invalid' => implode(', ', $invalid),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormSuccess(array &$form, FormStateInterface $form_state) {
    $config = $this->config('purge_queuer_pathalias.settings');
    $config->set('invalidation_type', (string) $form_state->getValue('invalidation_type'));
    $config->set('base_urls', (array) $form_state->getValue('base_urls'));
    foreach (['queue_insert', 'queue_update', 'queue_delete', 'expand_undefined_langcode', 'skip_syncing'] as $key) {
      $config->set($key, (bool) $form_state->getValue($key));
    }
    $langcodes = [];
    if ($this->languageManager->isMultilingual()) {
      $available = array_keys($this->languageManager->getLanguages());
      foreach ((array) $form_state->getValue('expand_langcodes') as $langcode) {
        if (is_string($langcode) && in_array($langcode, $available, TRUE)) {
          $langcodes[] = $langcode;
        }
      }
    }
    else {
      // Preserve what a multilingual sibling environment configured.
      $langcodes = (array) $config->get('expand_langcodes');
    }
    $config->set('expand_langcodes', array_values($langcodes));
    $config->set('max_per_request', (int) $form_state->getValue('max_per_request'));
    $config->save();
  }

}
