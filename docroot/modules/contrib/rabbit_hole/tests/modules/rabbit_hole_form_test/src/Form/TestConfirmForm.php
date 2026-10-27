<?php

namespace Drupal\rabbit_hole_form_test\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Url;

/**
 * A confirmation form for nodes, mirroring the Trash module's purge form.
 */
class TestConfirmForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('<front>');
  }

}
