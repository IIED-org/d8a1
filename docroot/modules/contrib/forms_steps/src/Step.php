<?php

namespace Drupal\forms_steps;

/**
 * A value object representing a step state.
 */
class Step implements StepInterface {

  /**
   * The forms_steps the step is attached to.
   *
   * @var \Drupal\forms_steps\FormsStepsInterface
   */
  protected $forms_steps;

  /**
   * The step's ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The step's label.
   *
   * @var string
   */
  protected $label;

  /**
   * The step's weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * The step's node_type.
   *
   * @var string
   */
  protected $nodeType;

  /**
   * The step's form_view_mode_id.
   *
   * @var string
   */
  protected $formMode;

  /**
   * The step's URL.
   *
   * @var string
   */
  protected $url;

  /**
   * The step's submit label.
   *
   * @var string
   */
  protected $submitLabel;

  /**
   * The step's cancel label.
   *
   * @var string
   */
  protected $cancelLabel;

  /**
   * The step's delete label.
   *
   * @var string
   */
  protected $deleteLabel;

  /**
   * The step's cancel route.
   *
   * @var string
   */
  protected $cancelRoute;

  /**
   * The step's cancel step.
   *
   * @var \Drupal\forms_steps\Step
   */
  protected $cancelStep;

  /**
   * The step's cancel step mode.
   *
   * @var string
   */
  protected $cancelStepMode;

  /**
   * The step's delete state.
   *
   * @var boolean
   */
  protected $hideDelete;

  /**
   * The step's previous label.
   *
   * @var string
   */
  protected $previousLabel;

  /**
   * The step's previous state.
   *
   * @var boolean
   */
  protected $displayPrevious;

  /**
   * Step constructor.
   *
   * @param \Drupal\forms_Steps\FormsStepsInterface $forms_steps
   *   The forms_steps the step is attached to.
   * @param string $id
   *   The step's ID.
   * @param string $label
   *   The step's label.
   * @param int $weight
   *   The step's weight.
   * @param string $nodeType
   *   The step's node type.
   * @param string $formMode
   *   The step's form mode.
   * @param string $url
   *   The step's URL.
   */
  public function __construct(FormsStepsInterface $forms_steps, $id, $label, $weight = 0, $nodeType, $formMode, $url) {
    $this->forms_steps = $forms_steps;
    $this->id = $id;
    $this->label = $label;
    $this->weight = $weight;
    $this->nodeType = $nodeType;
    $this->formMode = $formMode;
    $this->url = $url;
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
  public function nodeType() {
    return $this->nodeType;
  }

  /**
   * @inheritDoc
   */
  public function formMode() {
    return $this->formMode;
  }

  /**
   * @inheritDoc
   */
  public function Url() {
    return $this->url;
  }

  /**
   * Return a list of form modes available for this node type.
   *
   * @return array
   *    Returns the list of form modes.
   */
  public static function formModes() {
    $result = [];

    // Get the list of available form modes for a certain entity type.
    $form_modes = \Drupal::entityManager()->getFormModes('node');

    foreach ($form_modes as $form_mode) {
      $result[$form_mode['id']] = $form_mode['label'];
    }

    $result['default'] = 'Default';

    return $result;
  }

  /**
   * Gets the submit label.
   *
   * @return string
   *   The submit label.
   */
  public function submitLabel() {
    return $this->submitLabel;
  }

  /**
   * Gets the cancel label.
   *
   * @return string
   *   The cancel label.
   */
  public function cancelLabel() {
    return $this->cancelLabel;
  }

  /**
   * Gets the delete label.
   *
   * @return string
   *   The delete label.
   */
  public function deleteLabel() {
    return $this->deleteLabel;
  }

  /**
   * Gets the cancel route.
   *
   * @return string
   *   The cancel route.
   */
  public function cancelRoute() {
    return $this->cancelRoute;
  }

  /**
   * Gets the cancel step.
   *
   * @return \Drupal\forms_steps\Step
   *   The cancel step.
   */
  public function cancelStep() {
    return $this->cancelStep;
  }

  /**
   * Get the hidden status of the delete button.
   *
   * @return boolean
   *   true if hidden | false otherwise
   */
  public function hideDelete() {
    return $this->hideDelete;
  }

  /**
   * Set the hidden state of the delete button.
   *
   * @param  $value
   *   true if hidden | false otherwise
   */
  public function setHideDelete($value) {
    return $this->hideDelete = $value;
  }

  /**
   * Gets the cancel step mode.
   *
   * @return string
   *   The cancel step mode.
   */
  public function cancelStepMode() {
    return $this->cancelStepMode;
  }

  /**
   * @inheritDoc
   */
  public function setSubmitLabel($label) {
    $this->submitLabel = $label;
  }

  /**
   * @inheritDoc
   */
  public function setCancelLabel($label) {
    $this->cancelLabel = $label;
  }

  /**
   * @inheritDoc
   */
  public function setDeleteLabel($label) {
    $this->deleteLabel = $label;
  }

  /**
   * @inheritDoc
   */
  public function setCancelRoute($route) {
    $this->cancelRoute = $route;
  }

  /**
   * @inheritDoc
   */
  public function setCancelStep(Step $step) {
    $this->cancelStep = $step;
  }

  /**
   * @inheritDoc
   */
  public function setCancelStepMode($mode) {
    $this->cancelStepMode = $mode;
  }

  /**
   * @inheritDoc
   */
  public function formsSteps() {
    return $this->forms_steps;
  }

  /**
   * @inheritDoc
   */
  public function displayPrevious()
  {
    return $this->displayPrevious;
  }

  /**
   * @inheritDoc
   */
  public function setPreviousLabel($label)
  {
    $this->previousLabel = $label;
  }

  /**
   * @inheritDoc
   */
  public function previousLabel()
  {
    return $this->previousLabel;
  }

  /**
   * @inheritDoc
   */
  public function setDisplayPrevious($value) {
    return $this->displayPrevious = $value;
  }

}
