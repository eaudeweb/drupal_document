<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for DocumentCronManager class.
 */
class DocumentCronManager {

  /**
   * The document manager service.
   *
   * @var \Drupal\drupal_document\Services\DocumentManager
   */
  protected $documentManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a new CronManager object.
   *
   * @param \Drupal\drupal_document\Services\DocumentManager $documentManager
   *   The document manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File System service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger channel factory.
   */
  public function __construct(DocumentManager $documentManager, FileSystemInterface $fileSystem, LoggerChannelFactoryInterface $logger) {
    $this->fileSystem = $fileSystem;
    $this->logger = $logger->get('drupal_document');
    $this->documentManager = $documentManager;
  }

  /**
   * Remove empty directories.
   *
   * Archives are temporary files, they are deleted during cron but their
   * directories remains and needs to be deleted by cron.
   */
  public function cleaupOldDirectories() {
    $directoryPath = $this->getDirectoryPath();
    $dirs = array_keys(iterator_to_array(new \FilesystemIterator($directoryPath)));
    $count = 0;
    foreach ($dirs as $path) {
      $iterator = new \FilesystemIterator($path);
      if (!$iterator->valid()) {
        try {
          $this->fileSystem->rmdir($path);
          $count++;
        }
        catch (\Exception $exception) {
          $this->logger->warning($exception->getMessage());
        }
      }
    }
    $message = sprintf('Cleanup completed. Removed %s empty directories.', $count);
    $this->logger->notice($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    $directoryPath = $this->fileSystem->realpath($this->documentManager->getDirectoryRoot());
    if (substr($directoryPath, -1) != '/') {
      $directoryPath .= '/';
    }

    return $directoryPath;
  }

}
