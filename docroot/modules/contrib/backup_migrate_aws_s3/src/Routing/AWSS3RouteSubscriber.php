<?php

namespace Drupal\backup_migrate_aws_s3\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * AWS S3 Backup & Migrate Route Subscriber.
 */
class AWSS3RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.backup_migrate_destination.backup_download')) {
      $route->setDefaults([
        '_controller' => '\Drupal\backup_migrate_aws_s3\Controller\AWSS3BackupController::download',
      ]);
    }
  }

}
