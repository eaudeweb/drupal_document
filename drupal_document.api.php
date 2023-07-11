<?php

/**
 * @file
 * Hooks provided by the drupal_document module.
 */

/**
 * Alters field machine name used to get external links.
 *
 * @param string $machine_name
 *   The field's machine name.
 *
 * @see \Drupal\drupal_document\Form\DownloadDocumentsForm::alterExternalLinkField()
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_field_external_links_alter(string &$machine_name) {
}
