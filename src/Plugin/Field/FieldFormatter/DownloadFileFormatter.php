<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;

/**
 * Plugin implementation of the 'file_download_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "file_download_formatter",
 *   label = @Translation("Download File"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class DownloadFileFormatter extends GenericFileFormatter implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entity = $items->getEntity();

    return [
      '#theme' => 'download_modal',
      '#object' => $entity,
      '#button' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('document.modal', [
          'node' => $entity->id(),
          'field_name' => $items->getName(),
        ]),
        '#title' => $this->t('Download'),
        '#access' => $entity->access('view'),
        '#attributes' => [
          'class' => ['use-ajax', 'download-button'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => '400',
            'class' => ['download-modal'],
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax',
          ]
        ],
      ],
    ];
  }

}
