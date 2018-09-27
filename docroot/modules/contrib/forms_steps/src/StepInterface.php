<?php

namespace Drupal\forms_steps;

/**
 * An interface for step value objects.
 */
interface StepInterface {

  /**
   * Gets the step's ID.
   *
   * @return string
   *   The step's ID.
   */
  public function id();

  /**
   * Gets the step's label.
   *
   * @return string
   *   The step's label.
   */
  public function label();

  /**
   * Gets the step's weight.
   *
   * @return int
   *   The step's weight.
   */
  public function weight();

  /**
   * Gets the step's node type.
   *
   * @return string
   *   The step's node type.
   */
  public function nodeType();

  /**
   * Gets the step's form mode.
   *
   * @return string
   *   The step's form mode.
   */
  public function formMode();

  /**
   * Gets the step's url.
   *
   * @return string
   *   The step's url.
   */
  public function Url();

  /**
   * Gets the submit label.
   *
   * @return string
   *   The submit label.
   */
  public function submitLabel();

  /**
   * Set the submit label.
   *
   * @param $label
   */
  public function setSubmitLabel($label);

  /**
   * Gets the cancel label.
   *
   * @return string
   *   The cancel label.
   */
  public function cancelLabel();

  /**
   * Set the cancel label.
   *
   * @param $label
   */
  public function setCancelLabel($label);

  /**
   * Gets the cancel route.
   *
   * @return string
   *   The cancel route.
   */
  public function cancelRoute();

  /**
   * Set the cancel route.
   *
   * @param $route
   */
  public function setCancelRoute($route);

  /**
   * Gets the cancel step.
   *
   * @return \Drupal\forms_steps\Step
   *   The cancel step.
   */
  public function cancelStep();

  /**
   * Set the cancel step.
   *
   * @param \Drupal\forms_steps\Step $step
   */
  public function setCancelStep(Step $step);

  /**
   * Gets the cancel step mode.
   *
   * @return string
   *   The cancel step mode.
   */
  public function cancelStepMode();

  /**
   * Set the cancel step mode.
   *
   * @param mixed $mode
   *    Mode.
   */
  public function setCancelStepMode($mode);

  /**
   * Get the hidden status of the delete button.
   *
   * @return boolean
   *   true if hidden | false otherwise.
   */
  public function hideDelete();

  /**
   * Set the hidden state of the delete button.
   *
   * @param  $value
   *   true if hidden | false otherwise.
   */
  public function setHideDelete($value);

  /**
   * Set the delete label.
   *
   * @param mixed $label
   *    The label to set.
   */
  public function setDeleteLabel($label);

  /**
   * Get the forms steps object parent to this step.
   *
   * @return \Drupal\forms_steps\Step
   *    The forms steps object.
   */
  public function formsSteps();

  /**
   * Get the display status of the previous button.
   *
   * @return boolean
   *   true if displayed | false otherwise.
   */
  public function displayPrevious();

  /**
   * Set the previous label.
   *
   * @param mixed $label
   *    The label to set.
   */
  public function setPreviousLabel($label);

  /**
   * Gets the previous label.
   *
   * @return string
   *   The previous label.
   */
  public function previousLabel();

  /**
   * Set the display state of the previous button.
   *
   * @param  $value
   *   true if displayed | false otherwise.
   */
  public function setDisplayPrevious($value);

}
