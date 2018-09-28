<?php

namespace Drupal\forms_steps;

/**
 * A value object representing a progress step.
 */
class ProgressStep implements ProgressStepInterface {

  /**
   * The forms_steps the progress step is attached to.
   *
   * @var \Drupal\forms_steps\FormsStepsInterface
   */
  protected $forms_steps;

  /**
   * The progress step's ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The progress step's label.
   *
   * @var string
   */
  protected $label;

  /**
   * The progress step's weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * The progress step's active routes.
   *
   * @var string
   */
  protected $routes;

  /**
   * The progress step's link.
   *
   * @var string
   */
  protected $link;

  /**
   * The progress step's link visibility.
   *
   * @var array
   */
  protected $link_visibility;

  /**
   * Step constructor.
   *
   * @param \Drupal\forms_Steps\FormsStepsInterface $forms_steps
   *   The forms_steps the progress step is attached to.
   * @param string $id
   *   The progress step's ID.
   * @param string $label
   *   The progress step's label.
   * @param int $weight
   *   The progress step's weight.
   * @param array $routes
   *   The progress step's active routes.
   * @param string $link
   *   The progress step's link.
   * @param array $link_visibility
   *   The progress step's link visibility.
   */
  public function __construct(FormsStepsInterface $forms_steps, $id, $label, $weight = 0, array $routes, $link, array $link_visibility) {
    $this->forms_steps = $forms_steps;
    $this->id = $id;
    $this->label = $label;
    $this->weight = $weight;
    $this->routes = $routes;
    $this->link = $link;
    $this->link_visibility = $link_visibility;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function weight() {
    return $this->weight;
  }

  /**
   * @inheritDoc
   */
  public function activeRoutes() {
    return $this->routes;
  }

  /**
   * @inheritDoc
   */
  public function setActiveRoutes(array $routes) {
    return $this->routes = $routes;
  }

  /**
   * @inheritDoc
   */
  public function link() {
    return $this->link;
  }

  /**
   * @inheritDoc
   */
  public function setLink($link) {
    return $this->link = $link;
  }

  /**
   * @inheritDoc
   */
  public function linkVisibility() {
    return $this->link_visibility;
  }

  /**
   * @inheritDoc
   */
  public function setLinkVisibility(array $steps) {
    return $this->link_visibility = $steps;
  }

}
