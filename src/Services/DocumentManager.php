<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Service for DocumentManager.
 */
class DocumentManager {

  const ICONS_LABEL_INFO = [
    'pdf' => "PDF",
    'document' => "DOC",
    'spreadsheet' => "XLS",
    'presentation' => "PPT",
    'video' => "VIDEO",
    'text' => "TEXT",
    'image' => "IMG",
  ];

  /**
   * The current directory path.
   *
   * @var string
   */
  protected $directory;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Cached archive object.
   *
   * @var \Archive_Tar|\ZipArchive
   */
  protected $archive;

  /**
   * Constructs a new DocumentManager object.
   */
  public function __construct(CurrentRouteMatch $currentRouteMatch, EntityTypeManagerInterface $entityTypeManager, ModuleExtensionList $extensionListModule, FileUrlGeneratorInterface $fileUrlGenerator, FileSystemInterface $fileSystem, LanguageManagerInterface $languageManager, Connection $database) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->fileSystem = $fileSystem;
    $this->languageManager = $languageManager;
    $this->moduleExtensionList = $extensionListModule;
  }

  /**
   * Returns the file type based on uri extension.
   *
   * @param string $uri
   *   The URI to check.
   *
   * @return string|null
   *   The file type (pdf, text, document, presentation, spreadsheet, video,
   *   image).
   */
  public function getUriType(string $uri) {
    $extensionMapping = [
      'csv' => 'document',
      'doc' => 'document',
      'docx' => 'document',
      'fodg' => 'document',
      'fodt' => 'document',
      'odf' => 'document',
      'odg' => 'document',
      'odt' => 'document',
      'pages' => 'document',
      'rtf' => 'document',
      'pdf' => 'pdf',
      'txt' => 'text',

      'gif' => 'image',
      'jpg' => 'image',
      'jpeg' => 'image',
      'png' => 'image',
      'svg' => 'image',

      'key' => 'presentation',
      'fodp' => 'presentation',
      'odp' => 'presentation',
      'ppt' => 'presentation',
      'pptx' => 'presentation',

      'numbers' => 'spreadsheet',
      'fods' => 'spreadsheet',
      'ods' => 'spreadsheet',
      'xls' => 'spreadsheet',
      'xlsx' => 'spreadsheet',

      'shtml' => 'link',
      'htm' => 'link',

      'mp4' => 'video',
      'mov' => 'video',
      'avi' => 'video',
    ];

    $extension = pathinfo($uri, PATHINFO_EXTENSION);
    $extension = strtolower($extension);
    return !empty($extensionMapping[$extension]) ? $extensionMapping[$extension] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryRoot() {
    return Settings::get('drupal_document_directory') ?? 'public://downloads';
  }

  /**
   * If is given only a files, open in a new tab.
   *
   * @param array $url
   *   URL to find the file.
   *
   * @return string
   *   The URI of the file.
   */
  public function downloadFile(array $url) {
    $url = reset($url);
    $file = $this->entityTypeManager->getStorage('file')->loadByProperties([
      'uri' => $url,
    ]);
    $file = reset($file);
    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }

  /**
   * Archive files.
   *
   * Given a list of files, create an archive of those files and saves it as a
   * temporary file.
   *
   * @param array $filesUrls
   *   An array of urls.
   *
   * @return string
   *   URL to the zip file.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function archiveFiles(array $filesUrls) {
    $this->archive = new \ZipArchive();
    $this->archive->open($this->getArchiveFilePath(), \ZipArchive::CREATE);

    $fids = $this->entityTypeManager->getStorage('file')
      ->getQuery()
      ->accessCheck()
      ->condition('uri', $filesUrls, 'IN')
      ->execute();
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
    foreach ($files as $file) {
      $filepath = $file->getFileUri();
      if (!file_exists($filepath)) {
        continue;
      }
      $content = file_get_contents($filepath);

      if (!empty($content)) {
        $this->archive->addFromString($file->label(), $content);
      }
    }
    if ($this->archive->numFiles === 0) {
      return NULL;
    }
    $zipEntity = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $this->directory . DIRECTORY_SEPARATOR . 'documents.zip',
      'status' => 0,
    ]);
    $zipEntity->save();
    $this->archive->close();
    $this->archive = NULL;
    $this->directory = NULL;

    return $this->fileUrlGenerator->generateAbsoluteString($zipEntity->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public function getArchiveFilePath() {
    $date = new DrupalDateTime('now', 'UTC');
    $prefix = $date->format('d-m-Y') . '-';
    $this->directory = $this->getDirectoryRoot() . DIRECTORY_SEPARATOR . uniqid($prefix);
    $zipFileName = 'documents.zip';

    $this->fileSystem->prepareDirectory($this->directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $this->fileSystem->realpath($this->directory);
    return $destination . DIRECTORY_SEPARATOR . $zipFileName;
  }

  /**
   * Return formats and languages available for download.
   *
   * For a list with selected entities, return all formats and languages
   * available to download.
   *
   * @param array $nids
   *   An array of node IDs.
   * @param string $fieldName
   *   The name of the field to get files.
   *
   * @return array
   *   An array with options.
   */
  public function getOptions(array $nids, string $fieldName) {
    $availableLanguages = $availableFormats = [];
    $result = $this->database->select("node__{$fieldName}", 'f')
      ->fields('f', ["{$fieldName}_target_id", 'langcode']);
    $result->condition('f.entity_id', $nids, 'IN');
    $results = $result->execute()->fetchAll();
    $availableLanguages = array_column($results, 'langcode');
    $fids = array_column($results, "{$fieldName}_target_id");
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
    foreach ($files as $file) {
      if (!$file instanceof File) {
        continue;
      }
      $availableFormats[] = $this->getUriType($file->getFileUri());
    }
    return [array_unique($availableFormats), array_unique($availableLanguages)];
  }

  /**
   * Return a list with urls ready to be downloaded.
   *
   * @param array $nids
   *   An array of node IDs.
   * @param string $fieldName
   *   The name of the field to get files.
   * @param array $formats
   *   An array of selected formats.
   * @param array $languages
   *   An array of selected language codes.
   *
   * @return array
   *   An array with URLs.
   */
  public function getFilteredFiles(array $nids, string $fieldName, array $formats, array $languages) {
    $result = $this->database->select("node__{$fieldName}", 'f')->fields('f', ["{$fieldName}_target_id"]);
    $result->condition('f.entity_id', $nids, 'IN');
    $result->condition('f.langcode', $languages, 'IN');
    $fids = $result->execute()->fetchCol();
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
    $urls = [];
    foreach ($files as $file) {
      if (!$file instanceof File) {
        continue;
      }
      $url = $file->getFileUri();
      if (in_array($this->getUriType($url), $formats)) {
        $urls[] = $url;
      }
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcons(array $availableFormats) {
    $icons = $this->documentIconsPathInfo();
    $icons = array_filter($icons, function ($fileType) use ($availableFormats) {
      return in_array($fileType, $availableFormats);
    }, ARRAY_FILTER_USE_KEY);

    return array_map(function ($icon) use ($icons) {
      return !empty(array_search($icon, $icons)) ? DocumentManager::ICONS_LABEL_INFO[array_search($icon, $icons)] : '';
    }, $icons);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilteredLanguages(array $availableLanguages) {
    $languages = $this->languageManager->getLanguages();
    $languages = array_filter($languages, function (LanguageInterface $language) use ($availableLanguages) {
      return in_array($language->getId(), $availableLanguages);
    });
    return array_map(function (LanguageInterface $language) {
      return $language->getId();
    }, $languages);
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLinks(array $ids, string $linksFieldName) {
    $items = [];
    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    foreach ($entities as $entity) {
      $items = array_merge($items, $entity->get($linksFieldName)->getValue());
    }
    foreach ($items as &$item) {
      $item['options']['attributes']['class'][] = 'btn';
      $item['options']['attributes']['target'] = '_blank';
      $url = Url::fromUri($item['uri'], $item['options']);
      $link_title = !empty($item['title']) ? $item['title'] : 'Website';
      $item = [
        '#type' => 'link',
        '#title' => $link_title,
        '#url' => $url,
        '#options' => $url->getOptions(),
      ];
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function documentIconsPathInfo() {
    $iconsPath = sprintf('/%s/images/icons', $this->moduleExtensionList->getPath('drupal_document'));
    return [
      'pdf' => "$iconsPath/application-pdf.png",
      'document' => "$iconsPath/x-office-document.png",
      'spreadsheet' => "$iconsPath/x-office-spreadsheet.png",
      'presentation' => "$iconsPath/x-office-presentation.png",
      'video' => "$iconsPath/video-x-generic.png",
      'text' => "$iconsPath/text-plain.png",
      'image' => "$iconsPath/image-x-generic.png",
      'html' => "$iconsPath/text-html.png",
      'link' => "$iconsPath/text-html.png",
    ];
  }

}
