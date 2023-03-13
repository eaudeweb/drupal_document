<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
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
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['button_title'] = 'Download';

    return $settings;
  }

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
        '#title' => $this->getSetting('button_title'),
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
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The text of the button'),
      '#required' => TRUE,
      '#maxlength' => '254',
      '#default_value' => $this->getSetting('button_title'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('button_title')) {
      $summary[] = sprintf('%s: %s', $this->t('The text of the button'), $this->getSetting('button_title'));
    }

    return $summary;
  }

}
