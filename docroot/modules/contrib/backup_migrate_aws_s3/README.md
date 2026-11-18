# Backup and Migrate: AWS S3

This module allows for any AWS S3 storage to be used as a destination
for Backup and Migrate files.

## Requirements ##

Modules required:
- [Backup and Migrate](https://www.drupal.org/project/backup_migrate)
- [Key](https://www.drupal.org/project/key)

Optional:
- [Key AWS](https://www.drupal.org/project/key_aws)

## Installation

It is suggested that you install using Composer.

```bash
cd /path/to/drupal/root
composer require drupal/backup_migrate_aws_s3
drush en backup_migrate_aws_s3
```

## Configuration ##

1. Using the Key module, set up your access and secret keys. Keys can
be managed at (**/admin/config/system/keys**)

If using the Key AWS module, you can use the key for your AWS credentials
file instead. For configuration of this module, please see project page 
for further information.

2. Visit the Backup and Migrate Destinations settings page
(**/admin/config/development/backup_migrate/settings/destination**)

3. Add a new Backup Destination and choose "AWS S3" as the type.

4. Configure and select the keys configured earlier. Set region as well.
Bucket options will become available after required fields are entered in.

5. Now you are ready to start sending your backups to AWS S3 storage.


## Maintainers ##

George Anderson (geoanders)
https://www.drupal.org/u/geoanders
