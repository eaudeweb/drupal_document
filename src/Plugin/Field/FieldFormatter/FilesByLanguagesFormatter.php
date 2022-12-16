<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'files_group_by_language' formatter.
 *
 * @FieldFormatter(
 *   id = "files_group_by_language",
 *   label = @Translation("Files group by Language"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FilesByLanguagesFormatter extends FilesByLanguagesFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return parent::viewElements($items, $langcode);
  }
}
