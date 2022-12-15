<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'dropdown_file_language_formatter' formatter.
 *
 * This overrides the default "file_with_language" formatter and display only
 * files with language as a dropdown.
 *
 * @FieldFormatter(
 *   id = "dropdown_file_language_formatter",
 *   label = @Translation("Dropdown File with Language"),
 *   field_types = {
 *     "file_language"
 *   }
 * )
 */
class DropdownFilesFormatter extends FileWithLanguageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      if (!$item->language) {
        unset($elements[$delta]);
      }
    }

    return [
      '#theme' => 'dropdown_file_language',
      '#items' => $elements,
      '#object' => $items->getEntity(),
      '#attributes' => [
        'class' => [
          "download-files",
        ],
      ],
    ];
  }

}
