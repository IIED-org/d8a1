<?php

namespace Drupal\backup_migrate_aws_s3\Plugin\BackupMigrateDestination;

use Drupal\backup_migrate\Drupal\EntityPlugins\DestinationPluginBase;

/**
 * Defines a file directory destination plugin.
 *
 * @BackupMigrateDestinationPlugin(
 *   id = "awss3",
 *   title = @Translation("AWS S3"),
 *   description = @Translation("Backup to AWS S3 storage."),
 *   wrapped_class = "\Drupal\backup_migrate_aws_s3\Destination\AWSS3Destination"
 * )
 *
 * Note: It is unclear why the plugin is constructed with wrapped_class. To keep
 * things consistent, the deprecated Psr4ClassLoader is called in
 * backup_migrate_aws_s3.module.
 *
 * @see \Drupal\backup_migrate\Drupal\EntityPlugins\WrapperPluginBase::getObject()
 */
class AWSS3DestinationPlugin extends DestinationPluginBase {
}
