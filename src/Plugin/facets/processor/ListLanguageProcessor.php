<?php

namespace Drupal\drupal_document\Plugin\facets\processor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupal_document\Services\FileLanguageManager;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a processor that hides parents results and show only children.
 *
 * @FacetsProcessor(
 *   id = "file_language_processor",
 *   label = @Translation("List item language"),
 *   description = @Translation("Display the language instead of the langcode."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class ListLanguageProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\drupal_document\Services\FileLanguageManager
   */
  protected $fileLanguageManager;

  /**
   * Constructs a ListLanguageProcessor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\drupal_document\Services\FileLanguageManager $fileLanguageManager
   *   The File language manager.
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, FileLanguageManager $fileLanguageManager) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->fileLanguageManager = $fileLanguageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('file.language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $result) {
      $languageName = $this->fileLanguageManager->getLanguageName($result->getRawValue());
      if (empty($languageName)) {
        continue;
      }
      $result->setDisplayValue($languageName);
    }

    return $results;
  }

}
