<?php

namespace Drupal\cp_import\AppImport;

use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

/**
 * Contract for AppImport classes.
 */
interface BaseInterface {

  /**
   * Perform pre row save actions needed to be performed when the event.
   *
   * Is triggered.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The triggered event.
   */
  public function preRowSaveActions(MigratePreRowSaveEvent $event);

  /**
   * Perform Post row save actions.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The triggered event.
   */
  public function migratePostRowSaveActions(MigratePostRowSaveEvent $event);

  /**
   * Perform some actions while preparing row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The triggered event.
   *
   * @throws \Exception
   */
  public function prepareRowActions(MigratePrepareRowEvent $event);

  /**
   * Validates Rows for csv import.
   *
   * @param array $data
   *   Array derived from csv file.
   *
   * @return array
   *   Missing errors or empty if no errors.
   */
  public function validateRows(array $data);

  /**
   * Formats the error message to be displayed.
   *
   * @param string $rows
   *   Error rows.
   * @param string $message
   *   Message to be displayed.
   *
   * @return array
   *   Formatted error message and count.
   */
  public function getErrorMessage(string $rows, string $message): array;

}
