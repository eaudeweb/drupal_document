<?php

namespace Drupal\drupal_document\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
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
   */
  public function loadEntityFromBulkFormKey($encodedKey) {
    $key = base64_decode($encodedKey);
    $keyParts = json_decode($key);
    $revisionId = NULL;

    // If there are 4 items, the revision ID  will be last.
    if (count($keyParts) === 4) {
      $revisionId = array_pop($keyParts);
    }
    // Drop first element.
    array_shift($keyParts);
    // The first three items will always be the entity type, langcode and ID.
    [$langcode, $entityTypeId, $id] = $keyParts;
    // Load the entity or a specific revision depending on the given key.
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entity = $revisionId ? $storage->loadRevision($revisionId) : $storage->load($id);

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

}