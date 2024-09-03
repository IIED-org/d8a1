<?php

namespace Drupal\backup_migrate_aws_s3\Destination;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Drupal\backup_migrate\Core\Config\ConfigInterface;
use Drupal\backup_migrate\Core\Config\ConfigurableInterface;
use Drupal\backup_migrate\Core\Exception\BackupMigrateException;
use Drupal\backup_migrate\Core\Destination\RemoteDestinationInterface;
use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;
use Drupal\backup_migrate\Core\File\BackupFile;
use Drupal\backup_migrate\Core\Destination\ReadableDestinationInterface;
use Drupal\backup_migrate\Core\File\BackupFileInterface;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\backup_migrate\Core\File\ReadableStreamBackupFile;
use Drupal\backup_migrate\Core\Destination\DestinationBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * AWS S3 Backup & Migrate Destination.
 *
 * @package Drupal\backup_migrate_aws_s3\Destination
 */
class AWSS3Destination extends DestinationBase implements RemoteDestinationInterface, ListableDestinationInterface, ReadableDestinationInterface, ConfigurableInterface {

  use MessengerTrait;
  use LoggerChannelTrait;

  /**
   * Stores client.
   *
   * @var \Aws\S3\S3Client
   */
  protected $client = NULL;

  /**
   * Key repository service.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository = NULL;

  /**
   * File repository service.
   *
   * @var \Drupal\file\FileRepository
   */
  protected $fileRepository = NULL;

  /**
   * Filesystem service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem = NULL;

  /**
   * The AWS key repository object.
   *
   * @var \Drupal\key_aws\AWSKeyRepository
   */
  protected $awsKeyRepository = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigInterface $config) {
    parent::__construct($config);

    /** @codingStandardsIgnoreStart */
    $this->keyRepository = \Drupal::service('key.repository');
    $this->fileRepository = \Drupal::service('file.repository');
    $this->fileSystem = \Drupal::service('file_system');

    if (\Drupal::moduleHandler()->moduleExists('key_aws')) {
      $this->awsKeyRepository = \Drupal::service('key_aws.repository');
    }
    /** @codingStandardsIgnoreEnd */
  }

  /**
   * {@inheritdoc}
   */
  public function checkWritable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function deleteTheFile($id) {
    // Determine the filename, adding any folder prefix.
    $folder_prefix = '';
    if (!empty($this->confGet('s3_folder_prefix'))) {
      $folder_prefix = trim($this->confGet('s3_folder_prefix'));
      if (!empty($folder_prefix)) {
        $folder_prefix .= '/';
      }
    }

    // Delete an object from the bucket.
    try {
      $this->getClient()->deleteObject(
        [
          'Bucket' => $this->confGet('s3_bucket'),
          'Key' => $folder_prefix . $id,
        ]
      );
    }
    catch (AwsException $e) {
      $this->messenger()->addError($e->getAwsErrorMessage());
      watchdog_exception('backup_migrate_aws_s3', $e);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
   */
  protected function saveTheFile(BackupFileReadableInterface $file) {
    $this->saveFileToS3($file->getFullName(), $file->realpath(), $this->getClient());
  }

  /**
   * {@inheritdoc}
   */
  protected function saveTheFileMetadata(BackupFileInterface $file) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  protected function loadFileMetadataArray(BackupFileInterface $file) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($id) {
    // There is no way to fetch file info for a single file,
    // so we load them all.
    $files = $this->listFiles();
    if (isset($files[$id])) {
      return $files[$id];
    }

    return NULL;
  }

  /**
   * Download backup file.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileInterface $file
   *   Backup file.
   *
   * @return \Symfony\Component\HttpFoundation\Response|void
   *   Returns http file response.
   */
  public function downloadFile(BackupFileInterface $file) {
    $id = $file->getMeta('id');
    if ($this->fileExists($id)) {

      // Determine the filename, adding any folder prefix.
      $folder_prefix = '';
      if (!empty($this->confGet('s3_folder_prefix'))) {
        $folder_prefix = trim($this->confGet('s3_folder_prefix'));
        if (!empty($folder_prefix)) {
          $folder_prefix .= '/';
        }
      }

      // Fetch object using getObject().
      $result = NULL;
      try {
        $result = $this->getClient()->getObject(
          [
            'Bucket' => $this->confGet('s3_bucket'),
            'Key' => $folder_prefix . $id,
          ]
        );
      }
      catch (AwsException $e) {
        $this->messenger()->addError($e->getAwsErrorMessage());
        watchdog_exception('backup_migrate_aws_s3', $e);
      }

      if ($result) {
        return new Response(
          $result['Body'],
          Response::HTTP_OK,
          ['content-type' => $result['ContentType']]
        );
      }
      else {
        $this->getLogger('backup_migrate_aws_s3')->error('Failed to download backup from AWS S3.');
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function loadFileForReading(BackupFileInterface $file) {
    // If this file is already readable, simply return it.
    if ($file instanceof BackupFileReadableInterface) {
      return $file;
    }

    $id = $file->getMeta('id');
    if ($this->fileExists($id)) {

      // Determine the filename, adding any folder prefix.
      $folder_prefix = '';
      if (!empty($this->confGet('s3_folder_prefix'))) {
        $folder_prefix = trim($this->confGet('s3_folder_prefix'));
        if (!empty($folder_prefix)) {
          $folder_prefix .= '/';
        }
      }

      // Fetch object using getObject().
      $result = $this->getClient()->getObject(
        [
          'Bucket' => $this->confGet('s3_bucket'),
          'Key' => $folder_prefix . $id,
        ]
      );
      if ($result) {
        $file = $this->fileRepository->writeData($result['Body'], "temporary://$id", FileSystemInterface::EXISTS_REPLACE);
        if ($file) {
          return new ReadableStreamBackupFile($this->fileSystem->realpath($file->getFileUri()));
        }
      }
      else {
        $this->getLogger('backup_migrate_aws_s3')->error('Failed to download backup from AWS S3.');
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function fileExists($id): bool {
    return (boolean) $this->getFile($id);
  }

  /**
   * {@inheritdoc}
   */
  public function listFiles($count = 100, $start = 0): array {
    $file_list = [];
    $files = [];

    if (!empty($this->getClient())) {
      $iterator = $this->getClient()->getIterator('ListObjects', [
        'Bucket' => $this->confGet('s3_bucket'),
        'Prefix' => $this->confGet('s3_folder_prefix'),
      ]);

      foreach ($iterator as $object) {
        $file_list[] = $object;
      }

      // Determine the filename, adding any folder prefix.
      $folder_prefix = '';
      if (!empty($this->confGet('s3_folder_prefix'))) {
        $folder_prefix = trim($this->confGet('s3_folder_prefix'));
        if (!empty($folder_prefix)) {
          $folder_prefix .= '/';
        }
      }

      // Loop over objects pulled from S3.
      foreach ($file_list as $file) {
        // Use Key from S3 for filename.
        $filename = !empty($file['filename']) ? $file['filename'] : $file['Key'];

        // Check whether filename is exactly $folder_prefix.
        if ($filename == $folder_prefix) {
          // Yes; skip this row.
          continue;
        }

        // Check whether the filename begins with $folder_prefix.
        if (substr($filename, 0, strlen($folder_prefix)) == $folder_prefix) {
          $filename = substr($filename, strlen($folder_prefix));
          // Write the modified filename back into the $file array.
          if (!empty($file['filename'])) {
            $file['filename'] = $filename;
          }
          if (!empty($file['Key'])) {
            $file['Key'] = $filename;
          }
        }

        // Setup new backup file.
        $backupFile = new BackupFile();
        $backupFile->setMeta('id', $filename);
        $backupFile->setMeta('filesize', $file['Size']);
        $backupFile->setMeta('datestamp', strtotime($file['LastModified']));
        $backupFile->setFullName($filename);
        $backupFile->setMeta('metadata_loaded', TRUE);

        // Add backup file to files array.
        $files[$filename] = $backupFile;
      }
    }

    // Return files.
    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function countFiles() {
    $file_list = $this->listFiles();
    return count($file_list);
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): ?S3Client {
    // Check to see if client is already set.
    if ($this->client == NULL) {

      // Make sure we have region selected.
      if ($aws_region = $this->confGet('s3_region')) {
        $aws_key = NULL;
        $aws_secret = NULL;

        // Check to see if key_aws module is installed.
        if ($this->awsKeyRepository && $this->confGet('s3_key_name')) {
          // Pull credentials from AWS file.
          $this->awsKeyRepository->setKey($this->confGet('s3_key_name'));
          if ($this->awsKeyRepository->getCredentials()) {
            $aws_key = $this->awsKeyRepository->getAccessKey();
            $aws_secret = $this->awsKeyRepository->getSecretKey();
          }
        }
        else {
          // Access key.
          if ($this->confGet('s3_access_key_name')) {
            $aws_key = $this->keyRepository->getKey($this->confGet('s3_access_key_name'))->getKeyValue();
          }

          // Secret key.
          if ($this->confGet('s3_secret_key_name')) {
            $aws_secret = $this->keyRepository->getKey($this->confGet('s3_secret_key_name'))->getKeyValue();
          }
        }

        // AWS S3 configuration.
        $aws_config = [
          'version' => 'latest',
          'region' => $aws_region,
        ];

        // Set credentials.
        if ($aws_key && $aws_secret) {
          $aws_credentials = [
            'key' => $aws_key,
            'secret' => $aws_secret,
          ];
          $aws_config['credentials'] = $aws_credentials;
        }

        // Add in endpoint if configured.
        if ($aws_endpoint = $this->confGet('s3_endpoint')) {
          $aws_config['endpoint'] = $aws_endpoint;
        }

        // Set and initialize S3 client.
        $this->client = new S3Client($aws_config);
      }
      else {
        $this->messenger()->addError($this->t('Please fill all mandatory fields to create S3 client.'));
        $this->getLogger('backup_migrate_aws_s3')->error('Please fill all mandatory fields to create S3 client.');
      }
    }

    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function queryFiles($filters = [], $sort = 'datestamp', $sort_direction = SORT_DESC, $count = 100, $start = 0) {
    // Get the full list of files.
    $out = $this->listFiles($count + $start);
    foreach ($out as $key => $file) {
      $out[$key] = $this->loadFileMetadata($file);
    }

    // Filter the output.
    $out = array_reverse($out);

    // Slice the return array.
    if ($count || $start) {
      $out = array_slice($out, $start, $count);
    }

    return $out;
  }

  /**
   * Init configurations.
   */
  public function configSchema($params = []): array {
    $schema = [];

    // Init settings.
    if ($params['operation'] == 'initialize') {
      $key_collection_url = Url::fromRoute('entity.key.collection')->toString();

      // Get available keys.
      $keys = $this->keyRepository->getKeys();
      $key_options = [];
      foreach ($keys as $key_id => $key) {
        $key_options[$key_id] = $key->label();
      }

      // Host/endpoint.
      $schema['fields']['s3_endpoint'] = [
        'type' => 'text',
        'title' => $this->t('S3 Endpoint/Host'),
        'description' => $this->t('Enter Host/Endpoint name. For e.g. <i>https://s3.amazonaws.com</i>'),
      ];

      if ($this->awsKeyRepository) {
        // Credentials key.
        $schema['fields']['s3_key_name'] = [
          'type' => 'enum',
          'title' => $this->t('S3 Credentials Key'),
          'description' => $this->t('Credentials key to use AWS S3 client. Use keys managed by the key module. <a href=":keys">Manage keys</a>', [
            ':keys' => $key_collection_url,
          ]),
          'empty_option' => $this->t('- Select Key -'),
          'options' => $key_options,
          'required' => FALSE,
        ];
      }
      else {
        // Access key.
        $schema['fields']['s3_access_key_name'] = [
          'type' => 'enum',
          'title' => $this->t('S3 Access Key'),
          'description' => $this->t('Access key to use AWS S3 client. Use keys managed by the key module. <a href=":keys">Manage keys</a>', [
            ':keys' => $key_collection_url,
          ]),
          'empty_option' => $this->t('- Select Key -'),
          'options' => $key_options,
          'required' => FALSE,
        ];

        // Secret key.
        $schema['fields']['s3_secret_key_name'] = [
          'type' => 'enum',
          'title' => $this->t('S3 Secret Key'),
          'description' => $this->t('Secret key to use AWS S3 client. Use keys managed by the key module. <a href=":keys">Manage keys</a>', [
            ':keys' => $key_collection_url,
          ]),
          'empty_option' => $this->t('- Select Key -'),
          'options' => $key_options,
          'required' => FALSE,
        ];
      }

      // Bucket name.
      $schema['fields']['s3_bucket'] = [
        'type' => 'text',
        'title' => $this->t('S3 Bucket'),
        'description' => $this->t('Bucket to use when storing the database export file.'),
        'required' => TRUE,
      ];

      // Folder prefix.
      $schema['fields']['s3_folder_prefix'] = [
        'type' => 'text',
        'title' => $this->t('Sub-folder within the bucket (optional)'),
        'required' => FALSE,
        'description' => $this->t('If you wish to organise your backups into a sub-folder such as /my/subfolder/, enter <i>my/subfolder</i> here without the leading or trailing slashes.<br><strong>Important:</strong> if you change this path, any previous backups whose path is different will no longer be shown, but they will remain in your S3 bucket.'),
      ];

      // Regions.
      $regions = [
        '' => $this->t('- Select Region -'),
        'af-south-1' => $this->t('af-south-1'),
        'ap-east-1' => $this->t('ap-east-1'),
        'ap-northeast-1' => $this->t('ap-northeast-1'),
        'ap-northeast-2' => $this->t('ap-northeast-2'),
        'ap-northeast-3' => $this->t('ap-northeast-3'),
        'ap-south-1' => $this->t('ap-south-1'),
        'ap-southeast-1' => $this->t('ap-southeast-1'),
        'ap-southeast-2' => $this->t('ap-southeast-2'),
        'ap-southeast-3' => $this->t('ap-southeast-3'),
        'ca-central-1' => $this->t('ca-central-1'),
        'cn-north-1' => $this->t('cn-north-1'),
        'cn-northwest-1' => $this->t('cn-northwest-1'),
        'eu-central-1' => $this->t('eu-central-1'),
        'eu-north-1' => $this->t('eu-north-1'),
        'eu-south-1' => $this->t('eu-south-1'),
        'eu-west-1' => $this->t('eu-west-1'),
        'eu-west-2' => $this->t('eu-west-2'),
        'eu-west-3' => $this->t('eu-west-3'),
        'me-south-1' => $this->t('me-south-1'),
        'sa-east-1' => $this->t('sa-east-1'),
        'us-east-1' => $this->t('us-east-1'),
        'us-east-2' => $this->t('us-east-2'),
        'us-gov-east-1' => $this->t('us-gov-east-1'),
        'us-gov-west-1' => $this->t('us-gov-west-1'),
        'us-west-1' => $this->t('us-west-1'),
        'us-west-2' => $this->t('us-west-2'),
      ];
      $schema['fields']['s3_region'] = [
        'type' => 'enum',
        'title' => $this->t('S3 Region'),
        'options' => $regions,
        'description' => $this->t('Region to use when storing the database export file.'),
        'required' => TRUE,
      ];
    }

    return $schema;
  }

  /**
   * Save backup file to AWS S3.
   *
   * @param string $filename
   *   Backup filename.
   * @param string $file_loc
   *   Backup real path.
   * @param \Aws\S3\S3Client|null $client
   *   AWS S3 client.
   *
   * @return mixed
   *   Returns mixed values.
   *
   * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
   */
  public function saveFileToS3(string $filename, string $file_loc, S3Client $client = NULL) {
    // Make sure we have client.
    if (!empty($client)) {

      // Determine the filename, adding any folder prefix.
      $folder_prefix = '';
      if (!empty($this->confGet('s3_folder_prefix'))) {
        $folder_prefix = trim($this->confGet('s3_folder_prefix'));
        if (!empty($folder_prefix)) {
          $folder_prefix .= '/';
        }
      }

      try {
        // Get bucket.
        $bucket = $this->confGet('s3_bucket');

        // The object.
        $object = [
          'Bucket' => $bucket,
          'Key' => $folder_prefix . $filename,
          'SourceFile' => $file_loc,
        ];

        // Get bucket lock configuration.
        if (!empty($bucket)) {
          try {
            $bucketConfig = $this->client->getObjectLockConfiguration(['Bucket' => $bucket]);
            // Check to see if bucket is locked.
            if ($bucketConfig && !empty($bucketConfig->get('ObjectLockConfiguration')) && $bucketConfig->get('ObjectLockConfiguration')['ObjectLockEnabled']) {
              $object['ChecksumAlgorithm'] = 'SHA256';
              $object['ContentSHA256'] = hash_file('sha256', $file_loc);
            }
          }
          catch (AwsException $e) {
            $this->messenger()->addError($e->getAwsErrorMessage());
            watchdog_exception('backup_migrate_aws_s3', $e);
          }
        }

        // Use putObject() to upload object into bucket.
        $result = $client->putObject($object);

        // Add status message and return result.
        $this->messenger()->addStatus($this->t('Your backup %backup has been saved to your S3 account.', ['%backup' => $result['ObjectURL']]));
        return $result;
      }
      catch (BackupMigrateException $e) {
        // Throw exception.
        throw new BackupMigrateException('Could not upload to S3: %err (code: %code)', [
          '%err' => $e->getMessage(),
          '%code' => $e->getCode(),
        ]);
      }
    }
    else {
      // Set error messages.
      $this->messenger()->addError($this->t('Please fill all mandatory fields to create S3 client.'));
      $this->getLogger('backup_migrate_aws_s3')->error('Please fill all mandatory fields to create S3 client.');

      // Throw exception.
      throw new BackupMigrateException('Could not upload to AWS S3.');
    }
  }

}
