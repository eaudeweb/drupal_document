<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'entity_reference_files_group_by_language' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_files_group_by_language",
 *   label = @Translation("Files group by Language"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferencedFilesByLanguagesFormatter extends FilesByLanguagesFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return parent::viewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that reference
    // media items.
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media');
  }
}
