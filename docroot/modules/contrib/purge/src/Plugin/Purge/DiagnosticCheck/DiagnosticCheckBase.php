<?php

namespace Drupal\purge\Plugin\Purge\DiagnosticCheck;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Plugin\PluginBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\Exception\CheckNotImplementedCorrectly;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes a diagnostic check that tests a specific purging requirement.
 */
abstract class DiagnosticCheckBase extends PluginBase implements DiagnosticCheckInterface {

  /**
   * The title of the check as described in the plugin's metadata.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $title;

  /**
   * The description of the check as described in the plugin's metadata.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $description;

  /**
   * The severity of the outcome of this check.
   *
   * This value corresponds to one of these constants:
   *    - DiagnosticCheckInterface::SEVERITY_INFO
   *    - DiagnosticCheckInterface::SEVERITY_OK
   *    - DiagnosticCheckInterface::SEVERITY_WARNING
   *    - DiagnosticCheckInterface::SEVERITY_ERROR.
   *
   * @var int
   */
  protected $severity;

  /**
   * A recommendation matching the severity level, may contain NULL.
   *
   * @var null|string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $recommendation;

  /**
   * Optional check outcome / value (e.g. version numbers), may contain NULL.
   *
   * @var mixed
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Late runtime helper to assure that ::run() got called (and only once).
   */
  protected function runCheck() {
    if (!is_null($this->severity)) {
      return;
    }
    $this->severity = $this->run();
    if (!is_int($this->severity)) {
      $class = $this->getPluginDefinition()['class'];
      throw new CheckNotImplementedCorrectly("Expected integer as return from $class::run()!");
    }
    if ($this->severity < -1 || $this->severity > 2) {
      $class = $this->getPluginDefinition()['class'];
      throw new CheckNotImplementedCorrectly("Invalid const returned by $class::run()!");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $this->runCheck();
    if (is_null($this->title)) {
      $this->title = $this->getPluginDefinition()['title'];
    }
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $this->runCheck();
    if (is_null($this->description)) {
      $this->description = $this->getPluginDefinition()['description'];
    }
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeverity() {
    $this->runCheck();
    return $this->severity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeverityString() {
    $this->runCheck();
    $mapping = [
      self::SEVERITY_INFO      => 'INFO',
      self::SEVERITY_OK        => 'OK',
      self::SEVERITY_WARNING   => 'WARNING',
      self::SEVERITY_ERROR     => 'ERROR',
    ];
    return $mapping[$this->getSeverity()];
  }

  /**
   * {@inheritdoc}
   */
  public function getRecommendation() {
    $this->runCheck();
    if ($this->recommendation) {
      return $this->recommendation;
    }
    else {
      return $this->getDescription();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->runCheck();
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsArray() {
    $this->runCheck();
    return [
      'title' => (string) $this->getTitle(),
      'value' => (string) $this->getValue(),
      'description' => (string) $this->getRecommendation(),
      'severity_status' => strtolower($this->getSeverityString()),
      'severity' => $this->getRequirementsSeverity(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsSeverity() {
    static $mapping;
    $this->runCheck();
    if (is_null($mapping)) {
      // Drupal 11.2 introduced the RequirementSeverity enum and Drupal 12
      // removed the REQUIREMENT_* constants from install.inc. Map onto the enum
      // where core provides it and fall back to the legacy integer constants
      // on older cores, so the same code runs on Drupal 10 through 12.
      $mapping = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.2.0',
        fn() => [
          self::SEVERITY_INFO    => RequirementSeverity::Info,
          self::SEVERITY_OK      => RequirementSeverity::OK,
          self::SEVERITY_WARNING => RequirementSeverity::Warning,
          self::SEVERITY_ERROR   => RequirementSeverity::Error,
        ],
        function () {
          include_once DRUPAL_ROOT . '/core/includes/install.inc';
          return [
            self::SEVERITY_INFO    => REQUIREMENT_INFO,
            self::SEVERITY_OK      => REQUIREMENT_OK,
            self::SEVERITY_WARNING => REQUIREMENT_WARNING,
            self::SEVERITY_ERROR   => REQUIREMENT_ERROR,
          ];
        },
      );
    }
    return $mapping[$this->getSeverity()];
  }

}
