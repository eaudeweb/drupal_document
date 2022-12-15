<?php

namespace Drupal\drupal_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;
use Drupal\drupal_document\Services\FileLanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_with_language_formatter' formatter.
 *
 * Extends generic File formatter with the possibility to display the Language
 * selected from a dropdown with languages. If "use_description_as_link_text" is
 * true, then display description if language is not selected. If both are empty
 * then display the filename.
 *
 * @FieldFormatter(
 *   id = "file_with_language_formatter",
 *   label = @Translation("File with Language"),
 *   field_types = {
 *     "file_language"
 *   }
 * )
 */
class FileWithLanguageFormatter extends GenericFileFormatter implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\drupal_document\Services\FileLanguageManager
   */
  protected $fileLanguageManager;

  /**
   * Constructor for FileWithLanguageFormatter.
   *
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definitions.
   * @param array $settings
   *   The settings.
   * @param string $label
   *   The label.
   * @param string $viewMode
   *   The view mode.
   * @param array $thirdPartySettings
   *   The third party settings.
   * @param \Drupal\drupal_document\Services\FileLanguageManager $fileLanguageManager
   *   The language manager.
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, $label, $viewMode, array $thirdPartySettings, FileLanguageManager $fileLanguageManager) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $label, $viewMode, $thirdPartySettings);
    $this->fileLanguageManager = $fileLanguageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('file.language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['use_description_as_link_text'] = FALSE;
    $settings['suppress_language'] = FALSE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $form = parent::settingsForm($form, $formState);

    $form['use_description_as_link_text'] = [
      '#title' => $this->t('Use description as link text'),
      '#description' => $this->t('Replace the file name by its description when available and the Language is not set'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('use_description_as_link_text'),
    ];

    $form['suppress_language'] = [
      '#title' => $this->t('Suppress the language with description as link text'),
      '#description' => $this->t('Use the description instead of Language when is available'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('suppress_language'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      // If use_description_as_link_text is set and it's no language, display
      // description instead of filename.
      $description = ($this->getSetting('use_description_as_link_text') && !$item->language) ? $item->description : NULL;
      // If language is available language.
      if ($item->language) {
        $description = $this->fileLanguageManager->getLanguageName($item->language);
      }
      // If suppress_language is set and description exists, override language
      // with description.
      if ($this->getSetting('suppress_language') && $item->description) {
        $description = $item->description;
      }
      $elements[$delta] = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $description,
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
      // Pass field item attributes to the theme function.
      if (isset($item->_attributes)) {
        $elements[$delta] += ['#attributes' => []];
        $elements[$delta]['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('use_description_as_link_text')) {
      $summary[] = $this->t('Use description as link text when the Language is not set');
    }

    if ($this->getSetting('suppress_language')) {
      $summary[] = $this->t('Suppress Language with description');
    }

    return $summary;
  }

}
