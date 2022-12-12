<?php

namespace Drupal\cp_import\AppImport\os_events_import;

use Drupal\Component\Utility\UrlHelper;
use Drupal\cp_import\AppImport\Base;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\vsite\Path\VsiteAliasRepository;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;

/**
 * Class Events AppImport.
 *
 * @package Drupal\cp_import\AppImport\os_events_import
 */
class AppImport extends Base {

  /**
   * Bundle type.
   *
   * @var string
   */
  protected $type = 'events';

  /**
   * Group plugin id.
   *
   * @var string
   */
  protected $groupPluginId = 'group_node:events';

  public const SUPPORTED_FORMAT = [
    'Y-m-d',
    'Y-m-d h:i A',
    'Y-m-d g:i A',
    'Y-n-j',
    'Y-n-j h:i A',
    'Y-n-j g:i A',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(CpImportHelper $cpImportHelper, VsiteAliasRepository $vsiteAliasRepository, LanguageManager $languageManager, EntityTypeManagerInterface $entity_type_manager, VsiteContextManagerInterface $vsite_context_manager) {
    parent::__construct($cpImportHelper, $vsiteAliasRepository, $languageManager, $entity_type_manager, $vsite_context_manager);
    $appKeys = [
      'Title',
      'Body',
      'Start date',
      'End date',
      'Location',
      'Registration',
      'Files',
      'Created date',
      'Path',
    ];
    $this->sourceKeys = array_merge($this->sourceKeys, $appKeys);
  }

  /**
   * {@inheritdoc}
   */
  public function preRowSaveActions(MigratePreRowSaveEvent $event) {
    $row = $event->getRow();
    $dest_val = $row->getDestination();
    $media_val = $dest_val['field_attached_media']['target_id'];
    // If not a valid url return and don't do anything , this reduces the risk
    // of malicious scripts as we do not want to support HTML media from here.
    if (!UrlHelper::isValid($media_val)) {
      return;
    }
    // Get the media.
    $media_entity = $this->cpImportHelper->getMedia($media_val, $this->type, 'field_attached_media');
    if ($media_entity) {
      $row->setDestinationProperty('field_attached_media/target_id', $media_entity->id());
    }
    $row->setDestinationProperty('field_recurring_date/timezone', date_default_timezone_get());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRowActions(MigratePrepareRowEvent $event) {
    $source = $event->getRow()->getSource();

    $start_date = $source['Start date'];
    $end_date = $source['End date'];
    if ($start_date && strtotime($start_date)) {
      $date = DrupalDateTime::createFromTimestamp(strtotime($start_date));
      $event->getRow()->setSourceProperty('Start date', $date->format('Y-m-d h:i A'));
    }
    if ($end_date && strtotime($end_date)) {
      if ($start_date && (strtotime($start_date) == strtotime($end_date))) {
        // If start and end date are same, make it an all day event.
        $date = DrupalDateTime::createFromTimestamp(strtotime('+86399  seconds', strtotime($end_date)));
      }
      else {
        $date = DrupalDateTime::createFromTimestamp(strtotime($end_date));
      }
      $event->getRow()->setSourceProperty('End date', $date->format('Y-m-d h:i A'));
    }

    $signup_status = $source['Registration'];
    $signup_flag = FALSE;
    if (strtolower($signup_status) == 'on') {
      $signup_flag = TRUE;
    }
    $event->getRow()->setSourceProperty('Registration', $signup_flag);
    parent::prepareRowActions($event);
  }

  /**
   * Validates headers from csv file array.
   *
   * @param array $data
   *   Array derived from csv file.
   *
   * @return array
   *   Missing errors or empty if no errors.
   */
  public function validateHeaders(array $data): array {
    $headerMissing = FALSE;
    $eventsHeaders = [
      'Title',
      'Body',
      'Start date',
      'End date',
      'Location',
      'Registration',
      'Files',
      'Created date',
      'Path',
    ];
    $missing = [
      '@Title' => '',
      '@Body' => '',
      '@Start date' => '',
      '@End date' => '',
      '@Location' => '',
      '@Registration' => '',
      '@Files' => '',
      '@Created date' => '',
      '@Path' => '',
    ];

    foreach ($data as $row) {
      $columnHeaders = array_keys($row);
      foreach ($eventsHeaders as $eventsHeader) {
        if (!in_array($eventsHeader, $columnHeaders)) {
          $missing['@' . $eventsHeader] = $this->t('<li> @column </li>', ['@column' => $eventsHeader]);
          $headerMissing = TRUE;
        }
      }
    }
    return $headerMissing ? $missing : [];
  }

  /**
   * Validates Rows for csv import.
   *
   * @param array $data
   *   Array derived from csv file.
   *
   * @return array
   *   Missing errors or empty if no errors.
   */
  public function validateRows(array $data) : array {
    $hasError = FALSE;
    $titleRows = '';
    $fileRows = '';
    $endDateRows = '';
    $startDateRows = '';
    $dateValidation = '';
    $noStartDate = '';
    $noEndDate = '';
    $message = [
      '@title' => '',
      '@file' => '',
      '@start_date' => '',
      '@end_date' => '',
      '@date' => '',
    ];

    // Check common validations.
    $msg_count = 0;
    $parent_msg = parent::validateRows($data);
    if ($parent_msg) {
      $message = array_merge($message, $parent_msg);
      $msg_count += $message['count'];
      unset($message['count']);
      $hasError = TRUE;
    }

    foreach ($data as $delta => $row) {
      $row_number = ++$delta;
      // Validate Title.
      if (!$row['Title']) {
        $titleRows .= $row_number . ',';
      }
      // Validate File url.
      if ($url = $row['Files']) {
        $headers = get_headers($url, 1);
        if (strpos($headers[0], '200') === FALSE) {
          $fileRows .= $row_number . ',';
        }
      }

      $start_date = 0;
      if ($row['Start date']) {
        $start_date = strtotime($row['Start date']);
        if (!$start_date) {
          $startDateRows .= $row_number . ',';
        }
      }
      else {
        $noStartDate .= $row_number . ',';
      }

      $end_date = 0;
      if ($row['End date']) {
        $end_date = strtotime($row['End date']);
        if (!$end_date) {
          $endDateRows .= $row_number . ',';
        }
      }
      else {
        $noEndDate .= $row_number . ',';
      }

      if ($start_date > $end_date) {
        $dateValidation .= $row_number . ',';
      }

    }
    $titleRows = rtrim($titleRows, ',');
    if ($titleRows) {
      $msg_arr = $this->getErrorMessage($titleRows, 'The Title is required.');
      $message['@title'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }
    $fileRows = rtrim($fileRows, ',');
    if ($fileRows) {
      $msg_arr = $this->getErrorMessage($fileRows, 'File url is invalid.');
      $message['@file'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    $startDateRows = rtrim($startDateRows, ',');
    if ($startDateRows) {
      $msg_arr = $this->getErrorMessage($startDateRows, 'Start date format is invalid.');
      $message['@start_date'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    $endDateRows = rtrim($endDateRows, ',');
    if ($endDateRows) {
      $msg_arr = $this->getErrorMessage($endDateRows, 'End date format is invalid.');
      $message['@end_date'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    $dateValidation = rtrim($dateValidation, ',');
    if ($dateValidation) {
      $msg_arr = $this->getErrorMessage($dateValidation, 'Start date should be less than end date.');
      $message['@date'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    $noEndDate = rtrim($noEndDate, ',');
    if ($noEndDate) {
      $msg_arr = $this->getErrorMessage($noEndDate, 'End date is required.');
      $message['@end_date'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    $noStartDate = rtrim($noStartDate, ',');
    if ($noStartDate) {
      $msg_arr = $this->getErrorMessage($noStartDate, 'Start date is required.');
      $message['@start_date'] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
      $hasError = TRUE;
    }

    if ($msg_count > 0) {
      $message['@summary'] = $this->t('The Import file has @count error(s). </br>', ['@count' => $msg_count]);
    }
    return $hasError ? $message : [];
  }

}
