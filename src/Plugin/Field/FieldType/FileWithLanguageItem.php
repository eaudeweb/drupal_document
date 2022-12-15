<?php

namespace Drupal\drupal_document\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation of the 'file_language' field type.
 *
 * @FieldType(
 *   id = "file_language",
 *   label = @Translation("File with Language"),
 *   description = @Translation("This field stores the ID of a file and Language langcode as an integer values."),
 *   category = @Translation("Reference"),
 *   default_widget = "file_generic_with_language",
 *   default_formatter = "file_with_language_formatter",
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class FileWithLanguageItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'language' => '',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $fieldDefinition) {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the file entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'display' => [
          'description' => 'Flag to control whether this file should be displayed when viewing content.',
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'default' => 1,
        ],
        'description' => [
          'description' => 'A description of the file.',
          'type' => 'text',
        ],
        'language' => [
          'description' => 'The language of the file.',
          'type' => 'text',
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'file_managed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $fieldDefinition) {
    $properties = parent::propertyDefinitions($fieldDefinition);
    $properties['language'] = DataDefinition::create('string')
      ->setLabel(t('Language'));

    return $properties;
  }

}
