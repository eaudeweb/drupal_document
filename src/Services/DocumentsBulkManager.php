<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Defines an actions-based bulk operation form element.
 */
class DocumentsBulkManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DocumentsBulkManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Loads an entity based on a bulk form key.
   *
   * This is a slightly changed copy of the parent's method, except that the
   * entity type ID is not view based but is extracted from the bulk form key.
   *
   * @param string $encodedKey
   *   The bulk form key representing the entity's id, language and revision (if
   *   applicable) as one string.
   * @param bool $isSearchApi
   *   Flag to indicate bulk type.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity loaded in the state (language, optionally revision) specified
   *   as part of the bulk form key.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   *
   * @see SearchApiBulkForm::loadEntityFromBulkFormKey()
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  public function loadEntityFromBulkFormKey($encodedKey, bool $isSearchApi = TRUE) {
    $key = base64_decode($encodedKey);
    $keyParts = json_decode($key);

    [$revisionId, $langcode, $entityTypeId, $id] = ($isSearchApi) ? $this->searchApiBulkForm($keyParts) : $this->bulkForm($keyParts);

    // Load the entity or a specific revision depending on the given key.
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entity = $revisionId ? $storage->loadRevision($revisionId) : $storage->load($id);

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

  /**
   * Get the values for Node Bulk form.
   *
   * @param array $keyParts
   *   The bulk form key, representing the entity's id, language and
   *   revision (if applicable) as array.
   *
   * @return array
   *   The bulk form key, representing the entity's id, language and
   *   revision (if applicable) as variables.
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  private function bulkForm(array $keyParts) {
    // If there are 3 items, vid will be last.
    $revisionId = (count($keyParts) === 3) ? array_pop($keyParts) : NULL;
    // The first two items will always be langcode and ID.
    $id = array_pop($keyParts);
    $langcode = array_pop($keyParts);

    return [$revisionId, $langcode, 'node', $id];
  }

  /**
   * Get the values for SearchAPI Bulk form.
   *
   * @param array $keyParts
   *   The bulk form key, representing the entity's id, language and
   *   revision (if applicable) as array.
   *
   * @return array
   *   The bulk form key, representing the entity's id, language and
   *   revision (if applicable) as variables.
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  private function searchApiBulkForm(array $keyParts) {
    // If there are 4 items, the revision ID  will be last.
    $revisionId = (count($keyParts) === 4) ? array_pop($keyParts) : NULL;
    // Drop first element.
    array_shift($keyParts);
    // The first three items will always be the entity type, langcode and ID.
    [$langcode, $entityTypeId, $id] = $keyParts;

    return [$revisionId, $langcode, $entityTypeId, $id];
  }

}
