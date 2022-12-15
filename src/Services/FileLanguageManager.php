<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Local class responsible for providing language support.
 */
class FileLanguageManager {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * Gets the name of the language.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return string|null
   *   The human-readable name of the language.
   */
  public function getLanguageName(string $langcode) {
    $languages = $this->languageManager->getStandardLanguageList();
    if ($languages[$langcode]) {
      return $languages[$langcode][0];
    }

    return NULL;
  }

  /**
   * Some common languages with their English and native names.
   *
   * @return array
   *   An array of language code to language name information. Language name
   *   information itself is an array of English and native names.
   */
  public function getStandardLanguageList() {
    return $this->languageManager->getStandardLanguageList();
  }

}
