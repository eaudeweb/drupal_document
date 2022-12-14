<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;

/**
 * Service DocumentManager.
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
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

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
  public function __construct(CurrentRouteMatch $currentRouteMatch, EntityTypeManagerInterface $entityTypeManager, ModuleExtensionList $extensionListModule,
                              FileUrlGeneratorInterface $fileUrlGenerator, FileSystemInterface $fileSystem, LanguageManagerInterface $languageManager) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->fileSystem = $fileSystem;
    $this->languageManager = $languageManager;
    $this->moduleExtensionList = $extensionListModule;
  }

  /**
   * Returns the file type based on file extension.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return string|null
   *   The file type (pdf, text, document, presentation, spreadsheet, video, image).
   */
  public function getFileType(FileInterface $file) {
    return $this->getUriType($file->getFileUri());
  }

  /**
   * Return files for a selected language.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   * @param string $languageId
   *   The language id.
   * @param string $fieldName
   *   The field where to search.
   *
   * @return array
   *   An array with URLs.
   */
  public function getFilesByLanguage(Node $node, string $languageId, string $fieldName) {
    if (!$node->hasTranslation($languageId)) {
      return [];
    }
    $translation = $node->getTranslation($languageId);
    $files = $translation->get($fieldName)->referencedEntities();

    $urls = [];
    foreach ($files as $file) {
      $fileUri = $file->getFileUri();
      if (!empty($this->getUriType($fileUri))) {
        $urls[] = $fileUri;
      }
    }

    return $urls;
  }

  /**
   * Returns the file type based on uri extension.
   *
   * @param string $uri
   *
   * @return string|null
   *   The file type (pdf, text, document, presentation, spreadsheet, video, image).
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
   * If is given only a files, open in a new tab.
   *
   * @param array $url
   *
   * @return string
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
   * Given a list of files, create an archive of those files and saves it as a
   * temporary file.
   *
   * @param array $filesUrls
   *
   * @return string
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
    $this->directory = 'public://downloads/' . uniqid();
    $zipFileName = 'documents.zip';

    $this->fileSystem->prepareDirectory($this->directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $this->fileSystem->realpath($this->directory);

    return $destination . DIRECTORY_SEPARATOR . $zipFileName;
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
   * @return string[]
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
