<?php

namespace Drupal\cp_import\AppImport\os_blog_import;

use Drupal\Component\Utility\UrlHelper;
use Drupal\cp_import\AppImport\Base;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\vsite\Path\VsiteAliasRepository;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;

/**
 * Class AppImport.
 *
 * @package Drupal\cp_import\AppImport\os_blog_import
 */
class AppImport extends Base {

  /**
   * Bundle type.
   *
   * @var string
   */
  protected $type = 'blog';

  /**
   * Group plugin id.
   *
   * @var string
   */
  protected $groupPluginId = 'group_node:blog';

  /**
   * {@inheritdoc}
   */
  public function __construct(CpImportHelper $cpImportHelper, VsiteAliasRepository $vsiteAliasRepository, LanguageManager $languageManager, EntityTypeManagerInterface $entity_type_manager, VsiteContextManagerInterface $vsite_context_manager) {
    parent::__construct($cpImportHelper, $vsiteAliasRepository, $languageManager, $entity_type_manager, $vsite_context_manager);
    $appKeys = [
      'Title',
      'Body',
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
    $blogHeaders = ['Title', 'Body', 'Files', 'Created date', 'Path'];
    $missing = [
      '@Title' => '',
      '@Body' => '',
      '@Files' => '',
      '@Created date' => '',
      '@Path' => '',
    ];

    foreach ($data as $row) {
      $columnHeaders = array_keys($row);
      foreach ($blogHeaders as $blogHeader) {
        if (!in_array($blogHeader, $columnHeaders)) {
          $missing['@' . $blogHeader] = $this->t('<li> @column </li>', ['@column' => $blogHeader]);
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
    $message = [
      '@title' => '',
      '@file' => '',
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
    if ($msg_count > 0) {
      $message['@summary'] = $this->t('The Import file has @count error(s). </br>', ['@count' => $msg_count]);
    }
    return $hasError ? $message : [];
  }

}
