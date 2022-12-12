<?php

/**
 * @file
 * Publication Import Batch functions.
 */

/**
 * Batch operation callback.
 *
 * @param array $entry
 *   Parsed entry array.
 * @param string $format_id
 *   Format plugin id.
 * @param array $context
 *   The batch context array, passed by reference.
 *
 * @throws \Drupal\Component\Plugin\Exception\PluginException
 */
function cp_import_publication_batch_callback(array $entry, string $format_id, array &$context) {
  if (!isset($context['results'])) {
    $context['results']['success'] = [];
    $context['results']['errors'] = [];
  }

  // Try to save entry as a new publication.
  /** @var \Drupal\cp_import\Helper\CpImportPublicationHelper $cpImportPublicationHelper */
  $cpImportPublicationHelper = \Drupal::service('cp_import.publication_helper');
  $result = $cpImportPublicationHelper->savePublicationEntity($entry, $format_id);
  // Needed for proper batch context messages.
  if (isset($result['success'])) {
    $context['results']['success'][] = $result['success'];
  }
  if (isset($result['error'])) {
    $context['results']['errors'][] = $result['error'];
  }
  if (isset($result['message'])) {
    $context['message'] = $result['message'];
  }
}

/**
 * Complete a batch process.
 *
 * @param bool $success
 *   A boolean indicating whether the batch has completed successfully.
 * @param array $results
 *   The value set in $context['results'] by callback_batch_operation().
 * @param array|bool $operations
 *   If $success is FALSE, contains the operations that remained unprocessed.
 */
function cp_import_publication_batch_finished($success, array $results, $operations) {
  $messenger = \Drupal::messenger();
  $translator = \Drupal::translation();
  if ($success) {
    $message = $translator->formatPlural(count($results['success']), 'One entity processed.', '@count entities processed.');

    if (!empty($results['errors'])) {
      $error_message = $translator->formatPlural(count($results['errors']), 'One entry has not been processed.', '@count entries has not been processed.');
      foreach ($results['errors'] as $error) {
        $messenger->addError($error);
      }
      $messenger->addError($error_message);
    }
  }
  else {
    $message = t('Finished with an error.');
  }

  $messenger->addStatus($message);
}
