<?php

namespace Drupal\synonyms\SynonymsService\Behavior;

/**
 * Interface of a synonyms behavior. All behaviors must implement it.
 */
interface SynonymsBehaviorInterface {

  /**
   * Get human readable title of this behavior.
   *
   * @return string
   *   The return title
   */
  public function getTitle();

}
