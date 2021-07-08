<?php

namespace Drupal\synonyms;

use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a collection of synonym provider plugins.
 */
class SynonymProviderPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\synonyms\SynonymsProviderInterface\SynonymsProviderInterface
   *   The return value
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

}
