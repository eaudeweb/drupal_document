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
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

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
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct($plugin_id, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings,
                              ElementInfoManagerInterface $elementInfo, LanguageManagerInterface $languageManager, RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings, $elementInfo);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->routeMatch = $routeMatch;
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
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
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
        if ($language->getId() == $entity->language()->getId() && $this->routeMatch->getRouteName() == 'entity.node.content_translation_add') {
          $elements['languages'][$language->getId()]['data'] = parent::formMultipleElements($this->getEmptyField($items), $form, $form_state);
          continue;
        }
        $translatedField = $entity->getTranslation($language->getId())->get($fieldName);
        $elements['languages'][$language->getId()]['data'] = parent::formMultipleElements($translatedField, $form, $form_state);
        continue;
      }

      $elements['languages'][$language->getId()]['data'] = parent::formMultipleElements($this->getEmptyField($items), $form, $form_state);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $this->extractFormValuesMultiLanguage($items, $form, $form_state);

    // Update reference to 'items' stored during upload to take into account
    // changes to values like 'alt' etc.
    // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::submit()
    $fieldName = $this->fieldDefinition->getName();
    $fieldState = static::getWidgetState($form['#parents'], $fieldName, $form_state);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $parentStorage */
    $parentStorage = $this->entityTypeManager->getStorage($items->getEntity()->getEntityTypeId());

    /** @var \Drupal\Core\Entity\ContentEntityBase $parentEntity */
    $parentEntity = $items->getEntity();

    if (!empty($parentEntity->id())) {
      /** @var \Drupal\Core\Entity\ContentEntityBase $original */
      $original = $parentStorage->load($parentEntity->id());
      if ($parentEntity->hasField('created')) {
        // Load entity because $items->getEntity()->created value is now instead of created time.
        $parentEntity->set('created', $original->get('created')->value);
      }
    }

    $translatableFields = $parentEntity->getTranslatableFields(FALSE);
    $parentValues = [];
    foreach ($translatableFields as $fieldName => $fieldConfig) {
      switch ($fieldConfig->getFieldDefinition()->getType()) {
        case 'entity_reference':
        case 'text_with_summary':
          $fieldValue = $parentEntity->get($fieldName)->getValue();
          break;

        default:
          $fieldValue = $parentEntity->get($fieldName)->value;
      }

      $parentValues[$fieldName] = $fieldValue;
    }

    $currentLangcode = $parentEntity->get('langcode')->value;
    $currentItems = [];
    foreach ($items->getValue() as $item) {
      $itemLanguage = $item['language'];
      if ($currentLangcode == $itemLanguage) {
        $currentItems[] = $item;
        continue;
      }

      $parentEntityTranslation = $parentEntity->hasTranslation($itemLanguage) ?
        $parentEntity->getTranslation($itemLanguage) :
        $parentEntity->addTranslation($itemLanguage, $parentValues);

      $translationValues = array_column($parentEntityTranslation->get($fieldName)->getValue(), 'target_id');
      if (in_array($item['target_id'], $translationValues)) {
        continue;
      }

      $parentEntityTranslation->get($fieldName)->appendItem($item);
    }

    $fieldState['items'] = $currentItems;
    $removed = 0;
    foreach ($items as $index => $item) {
      if (!in_array($item->target_id, array_column($currentItems, 'target_id'))) {
        $items->removeItem($index - $removed);
        $removed++;
      }
    }

    static::setWidgetState($form['#parents'], $fieldName, $form_state, $fieldState);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValuesMultiLanguage(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $fieldName = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $userInput = $form_state->getUserInput();
    $values = !empty($userInput[$fieldName]) ? $userInput[$fieldName] : [];
    if (!empty($values['languages'])) {
      foreach ($values['languages'] as $lang => &$files) {
        if (empty($files['data'])) {
          continue;
        }

        foreach ($files['data'] as &$file) {
          $file['fids'] = !empty($file['fids']) ? ((array) $file['fids']) : [];
        }
      }
    }

    if ($values) {
      // Account for drag-and-drop reordering if needed.
      if (!$this->handlesMultipleValues()) {
        // Remove the 'value' of the 'add more' button.
        unset($values['add_more']);

        $newValues = [];
        foreach ($values['languages'] as $language => $languageValues) {
          if (!is_array($languageValues) || !array_key_exists('data', $languageValues)) {
            continue;
          }

          foreach ($languageValues['data'] as $value) {
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

      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $fieldState = static::getWidgetState($form['#parents'], $fieldName, $form_state);
      foreach ($items as $delta => $item) {
        $fieldState['original_deltas'][$delta] = isset($item->_original_delta) ? $item->_original_delta : $delta;
        unset($item->_original_delta, $item->_weight);
      }
      static::setWidgetState($form['#parents'], $fieldName, $form_state, $fieldState);
    }
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

}
