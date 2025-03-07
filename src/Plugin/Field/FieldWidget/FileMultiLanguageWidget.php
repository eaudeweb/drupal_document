<?php

namespace Drupal\drupal_document\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'file_multi_language' widget.
 *
 * @FieldWidget(
 *   id = "file_multi_language",
 *   label = @Translation("Multi Language file"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileMultiLanguageWidget extends FileWidget {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructor for FileMultiLanguageWidget.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $thirdPartySettings
   *   Third party settings.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo
   *   The element info manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack object.
   */
  public function __construct($plugin_id, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings, ElementInfoManagerInterface $elementInfo, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack) {
    parent::__construct($plugin_id, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings, $elementInfo);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $pluginDefinition) {
    return new static(
      $plugin_id,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Overrides FileWidget::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $fieldName = $this->fieldDefinition->getName();
    $languages = $this->languageManager->getLanguages();
    $currentLangcode = $this->languageManager->getCurrentLanguage()->getId();

    /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
    $entity = $items->getEntity();
    $elements = [];
    $elements['#type'] = 'details';
    $elements['#open'] = TRUE;
    $elements['#title'] = $this->fieldDefinition->getLabel();

    $elements['languages'] = [
      '#type' => 'horizontal_tabs',
      '#group_name' => 'language_files',
      '#default_tab' => Html::cleanCssIdentifier("edit_{$fieldName}_{$currentLangcode}"),
    ];

    foreach ($languages as $language) {
      $elements['languages'][$language->getId()] = [
        '#type' => 'details',
        '#title' => $language->getName(),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#id' => Html::cleanCssIdentifier("edit_{$fieldName}_{$language->getId()}"),
      ];
      if ($entity->hasTranslation($language->getId())) {
        $translatedField = $entity->getTranslation($language->getId())->get($fieldName);
        $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
        if ($this->getCurrentTabLanguage() != $language->getId() && isset($field_state['items'])) {
          // If the current tab language doesn't match the language being
          // processed, removes any existing items from the field state to
          // ensure a correct generation for remove_buttons.
          unset($field_state['items']);
        }
        $elements['languages'][$language->getId()]['data'] = parent::formMultipleElements($translatedField, $form, $form_state);
        continue;
      }

      $elements['languages'][$language->getId()]['data'] = parent::formMultipleElements($this->getEmptyField($items), $form, $form_state);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Rewrite the parent::extractFormValues() from WidgetBase.
    $formValues = $this->extractFormValuesMultiLanguage($items, $form, $form_state);

    /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
    $entity = $items->getEntity();
    $translationFiles = [];

    foreach ($entity->getTranslationLanguages() as $language) {
      $translationFiles[$language->getId()] = [];
    }

    foreach ($formValues as $item) {
      $langcode = $item['language'];
      $translationFiles[$langcode][] = $item;
    }

    $fileFieldName = $this->fieldDefinition->getName();
    foreach ($translationFiles as $languageId => $translationFileItems) {
      $translation = $entity->hasTranslation($languageId) ?
        $entity->getTranslation($languageId) :
        $entity->addTranslation($languageId, $entity->toArray());
      $translation->set($fileFieldName, $translationFileItems);
    }
  }

  /**
   * Override the default WidgetBase::extractFormValues().
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   An array of the field values.
   * @param array $form
   *   The form structure where field elements are attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see WidgetBase::extractFormValues()
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function extractFormValuesMultiLanguage(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $fieldName = $this->fieldDefinition->getName();
    $requestValues = $this->request->request->all();
    $values = $requestValues[$fieldName] ?? [];
    if (!empty($values['languages'])) {
      foreach ($values['languages'] as &$files) {
        if (empty($files['data'])) {
          continue;
        }
        foreach ($files['data'] as $fileIndex => &$fileDataItem) {
          if (empty($fileDataItem['fids'])) {
            unset($files['data'][$fileIndex]);
            continue;
          }
          $fileDataItem['fids'] = !empty($fileDataItem['fids']) ? ((array) $fileDataItem['fids']) : [];
        }
      }
    }
    if ($values) {
      // Account for drag-and-drop reordering if needed.
      if (!$this->handlesMultipleValues()) {
        // Remove the 'value' of the 'add more' button.
        unset($values['add_more']);

        $newValues = [];
        foreach ($values['languages'] as $language => $data) {
          if (!is_array($data) || !array_key_exists('data', $data)) {
            continue;
          }
          foreach ($data['data'] as $value) {
            if (empty($value['fids'])) {
              continue;
            }
            $value['language'] = $language;
            $newValues[] = $value;
          }
        }

        $values = $newValues;

        // The original delta, before drag-and-drop reordering, is needed to
        // route errors to the correct form element.
        foreach ($values as $delta => &$value) {
          $value['_original_delta'] = $delta;
        }

        usort($values, function ($a, $b) {
          return SortArray::sortByKeyInt($a, $b, '_weight');
        });
      }

      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);

      $currentLangTab = $this->getCurrentTabLanguage();
      $currentTabItems = array_filter($values, function ($value) use ($currentLangTab) {
        if ($value['language'] == $currentLangTab) {
          return $value;
        }
      });

      // Assign the values and remove the empty ones.
      $items->setValue($currentTabItems);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = static::getWidgetState($form['#parents'], $fieldName, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
        unset($item->_original_delta, $item->_weight);
      }
      $field_state['items'] = $items->getValue();
      static::setWidgetState($form['#parents'], $fieldName, $form_state, $field_state);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function processMultiple($element, FormStateInterface $form_state, $form) {
    $triggeringElement = $form_state->getTriggeringElement();
    $buttons = $form_state->getButtons();
    if (empty($triggeringElement) || $form_state->getTriggeringElement()['#name'] == $form_state->getUserInput()['_triggering_element_name']) {
      return parent::processMultiple($element, $form_state, $form);
    }
    $currentTriggeringId = $form_state->getUserInput()['_triggering_element_name'];
    if (!in_array($currentTriggeringId, array_column($form_state->getButtons(), '#name'))) {
      return parent::processMultiple($element, $form_state, $form);
    }
    $key = array_search($currentTriggeringId, array_column($form_state->getButtons(), '#name'));
    $triggeringElement = $buttons[$key] ?? NULL;
    $form_state->setTriggeringElement($triggeringElement);
    return parent::processMultiple($element, $form_state, $form);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyField(FieldItemListInterface &$items) {
    while ($items->count()) {
      $items->removeItem(0);
    }
    return $items;
  }

  /**
   * Get the language of the active tab.
   *
   * @return string
   *   Return the langcode.
   */
  protected function getCurrentTabLanguage() {
    $requestValues = $this->request->request->all();
    if (!$requestValues) {
      return $this->languageManager->getCurrentLanguage()->getId();
    }
    $fileFieldName = $this->fieldDefinition->getName();
    $activeTabId = $requestValues[$fileFieldName]['languages']["{$fileFieldName}__languages__active_tab"];
    $langcode = explode('-', $activeTabId);
    return end($langcode);
  }

}
