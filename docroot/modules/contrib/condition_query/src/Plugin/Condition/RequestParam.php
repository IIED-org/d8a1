<?php

namespace Drupal\condition_query\Plugin\Condition;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Request Param' condition.
 *
 * @Condition(
 *   id = "request_param",
 *   label = @Translation("Request Param"),
 * )
 */
class RequestParam extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * An alias manager to find the alias for the current system path.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a RequestPath condition plugin.
   *
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(AliasManagerInterface $alias_manager, RequestStack $request_stack, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('path.alias_manager'),
      $container->get('request_stack'),
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['request_param' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['request_param'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query Parameters'),
      '#default_value' => $this->configuration['request_param'],
      '#description' => $this->t("Specify the request parameters. Enter one parameter per line. The '*' character is a wildcard. Examples: %example_1 and %example_2 and %example_3.", [
        '%example_1' => 'visibility=show',
        '%example_2' => 'visibility[]=show',
        '%example_3' => 'visibility=*',
      ]),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['request_param'] = $form_state->getValue('request_param');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $params = array_map('trim', explode("\n", $this->configuration['request_param']));
    $params = implode(', ', $params);
    if (!empty($this->configuration['negate'])) {
      return $this->t('Do not return true on the following query parameters: @params', ['@params' => $params]);
    }
    return $this->t('Return true on the following query parameters: @params', ['@params' => $params]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Convert params to lowercase.
    $params = $this->configuration['request_param'];
    if (!$params) {
      return TRUE;
    }

    $request = $this->requestStack->getCurrentRequest();
    parse_str(preg_replace('/\n|\r\n?/', '&', $params), $request_params);
    if (!empty($request_params)) {
      foreach ($request_params as $key => $values) {
        $query_param_value = $request->get($key);

        // Checking for the wildcard
        if (!is_array($values) && $values == '*' && isset($query_param_value)) {
          return TRUE;
        }

        if (!is_array($values)) {
          $values = [$values];
        }
        if (is_array($query_param_value)) {
          foreach ($query_param_value as $array_value) {
            if (in_array($array_value, $values)) {
              return TRUE;
            }
          }
        }
        elseif (in_array($query_param_value, $values)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.query_args';
    return $contexts;
  }

}
