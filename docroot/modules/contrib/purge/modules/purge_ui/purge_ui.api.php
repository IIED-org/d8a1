<?php

/**
 * @file
 * Hooks provided by the Purge UI module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the expressions the "Purge this page" block queues or purges.
 *
 * \Drupal\purge_ui\Form\PurgeBlockForm derives its expressions from the
 * current request: the URL(s) of the page for blocks of type 'url', the
 * request path for blocks of type 'path', and a single NULL expression for
 * blocks of type 'everything'. Implementations can add related expressions
 * (for example the source URLs of redirects pointing at the current page) or
 * remove ones they do not want purged.
 *
 * Every expression must be valid for the invalidation type given in
 * $config['type'], because all of them are turned into invalidation objects of
 * that type.
 *
 * @param string[] $expressions
 *   The expressions to be queued or purged, keyed numerically.
 * @param array $config
 *   The block configuration, including 'type' ('url', 'path' or
 *   'everything'), 'execution' ('direct' or 'queue') and 'purge_block_id'.
 *
 * @see \Drupal\purge_ui\Form\PurgeBlockForm::gatherInvalidationsData()
 */
function hook_purge_ui_block_expressions_alter(array &$expressions, array $config): void {
  // Also purge the redirects that point at the node being viewed.
  if ($config['type'] !== 'url') {
    return;
  }
  $route_match = \Drupal::routeMatch();
  if ($route_match->getRouteName() !== 'entity.node.canonical') {
    return;
  }
  $nid = $route_match->getParameter('node')->id();
  $redirects = \Drupal::service('redirect.repository')
    ->findByDestinationUri(["internal:/node/$nid", "entity:node/$nid"]);
  foreach ($redirects as $redirect) {
    $expressions[] = \Drupal::request()
      ->getUriForPath($redirect->getSourceUrl());
  }
}

/**
 * @} End of "addtogroup hooks".
 */
