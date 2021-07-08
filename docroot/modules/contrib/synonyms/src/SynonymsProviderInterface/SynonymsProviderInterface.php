<?php

namespace Drupal\synonyms\SynonymsProviderInterface;

/**
 * Most generic synonyms provider interface. All providers must implement it.
 */
interface SynonymsProviderInterface {

  /**
   * Fetch behavior service which corresponds to this provider.
   *
   * @return string
   *   Name of synonyms behavior service which corresponds to this provider
   */
  public function getBehaviorService();

  /**
   * Fetch behavior service instance which corresponds to this provider.
   *
   * @return \Drupal\synonyms\SynonymsService\Behavior\SynonymsBehaviorInterface
   *   The return value
   */
  public function getBehaviorServiceInstance();

}
