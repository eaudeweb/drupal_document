<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\drupal_document\Services\DocumentManager;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The document manager.
   *
   * @var \Drupal\drupal_document\Services\DocumentManager*/
  protected $documentManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DocumentManager $documentManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->documentManager = $documentManager;
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
      $container->get('document.manager'),
    );
  }

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

    // Downloaded directly when is only one file or files with same format and
    // the same language.
    [$formats, $languages] = $this->documentManager->getOptions([$entity->id()], $items->getName());
    if (count($formats) == 1 && count($languages) == 1) {
      $filesUrls = $this->documentManager->getFilteredFiles([$entity->id()], $items->getName(), $formats, $languages);
      $path = (count($filesUrls) < 2) ? $this->documentManager->downloadFile($filesUrls) : $this->documentManager->archiveFiles($filesUrls);
      return [
        '#type' => 'link',
        '#url' => Url::fromUri($path, [
          'attributes' => [
            'target' => '_blank',
          ],
        ]),
        '#title' => $this->getSetting('button_title'),
        '#access' => $entity->access('view'),
        '#attributes' => [
          'class' => ['download-button'],
        ],
      ];
    }

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
