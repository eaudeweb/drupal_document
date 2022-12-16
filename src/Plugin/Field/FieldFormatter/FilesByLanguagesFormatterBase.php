<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Base class for Files by Languages formatters.
 */
class FilesByLanguagesFormatterBase extends EntityReferenceFormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a FilesByLanguagesFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings,
                              LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileStorage = $this->entityTypeManager->getStorage('file');
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      $langcode = $language->getId();
      $elements[$langcode] = [];
      $this->getLanguageElements($elements, $items, $langcode);
    }
    if (empty($elements)) {
      return [];
    }

    ksort($elements);

    return [
      '#theme' => 'files_group_by_language',
      '#rows' => $elements,
      '#current_language' => $this->languageManager->getCurrentLanguage()->getId(),
    ];
  }

  /**
   * Builds a renderable array.
   *
   * @param array $elements
   *   A renderable array for a themed field with its label and all its values.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   */
  public function getLanguageElements(&$elements, FieldItemListInterface $items, string $langcode) {
    $entity = $items->getEntity();
    if ($entity->hasTranslation($langcode)) {
      $translation = $entity->getTranslation($langcode);
      $items = $translation->get($this->fieldDefinition->getName());
      /** @var \Drupal\file\Entity\File $file */
      foreach ($this->getEntitiesToView($items, $langcode) as $source) {
        $file = ($source instanceof Media) ? $this->fileStorage->load($source->getSource()->getSourceFieldValue($source)) : $source;
        if (!$file instanceof File) {
          continue;
        }
        $item = $file->_referringItem;
        $element = [
          'filemime' => [
            '#type' => 'markup',
            '#markup' => pathinfo($file->getFileUri())['extension'],
          ],
          'content' => [
            '#theme' => 'file_link',
            '#file' => $file,
            '#description' => $file->getFilename(),
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ],
        ];
        // Pass field item attributes to the theme function.
        if (isset($item->_attributes)) {
          $element += ['#attributes' => []];
          $element['#attributes'] += $item->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and should not be rendered in the field template.
          unset($item->_attributes);
        }
        $elements[$langcode][] = $element;
      }
    }
  }

  /**
   * Returns the referenced entities for display.
   *
   * The method takes care of:
   * - checking entity access,
   * - placing the entities in the language expected for display.
   * It is thus strongly recommended that formatters use it in their
   * implementation of viewElements($items) rather than dealing with $items
   * directly.
   *
   * For each entity, the EntityReferenceItem by which the entity is referenced
   * is available in $entity->_referringItem. This is useful for field types
   * that store additional values next to the reference itself.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items
   *   The item list.
   * @param string $langcode
   *   The language code of the referenced entities to display.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The array of referenced entities to display, keyed by delta.
   *
   * @see ::getEntitiesToView()
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);

    foreach ($items as $delta => $item) {
      $entity = $item->entity;
      if (!$entity instanceof EntityInterface) {
        continue;
      }

      // Set the entity in the correct language for display.
      if ($entity instanceof TranslatableInterface) {
        $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
      }

      $access = $this->checkAccess($entity);
      // Add the access result's cacheability, ::view() needs it.
      $item->_accessCacheability = CacheableMetadata::createFromObject($access);
      if ($access->isAllowed()) {
        // Add the referring item, in case the formatter needs it.
        $entity->_referringItem = $items[$delta];
        $entities[$delta] = $entity;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return parent::isApplicable($field_definition);
  }

}
