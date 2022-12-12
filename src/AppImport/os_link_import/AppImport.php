<?php

namespace Drupal\cp_import\AppImport\os_link_import;

use Drupal\Component\Utility\UrlHelper;
use Drupal\cp_import\AppImport\Base;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\vsite\Path\VsiteAliasRepository;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

/**
 * Class AppImport.
 *
 * @package Drupal\cp_import\AppImport\os_link_import
 */
class AppImport extends Base {

  /**
   * Bundle type.
   *
   * @var string
   */
  protected $type = 'link';

  /**
   * Group plugin id.
   *
   * @var string
   */
  protected $groupPluginId = 'group_node:link';

  /**
   * {@inheritdoc}
   */
  public function __construct(CpImportHelper $cpImportHelper, VsiteAliasRepository $vsiteAliasRepository, LanguageManager $languageManager, EntityTypeManagerInterface $entity_type_manager, VsiteContextManagerInterface $vsite_context_manager) {
    parent::__construct($cpImportHelper, $vsiteAliasRepository, $languageManager, $entity_type_manager, $vsite_context_manager);
    $appKeys = [
      'Title',
      'Body',
      'URL',
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
   * {@inheritdoc}
   */
  public function prepareRowActions(MigratePrepareRowEvent $event) {
    $source = $event->getRow()->getSource();
    $links = [
      [
        'title' => '',
        'uri' => $source['URL'],
      ],
    ];
    $event->getRow()->setSourceProperty('URL', $links);
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
    $linkHeaders = ['Title', 'Body', 'URL', 'Files', 'Created date', 'Path'];
    $missing = [
      '@Title' => '',
      '@Body' => '',
      '@URL' => '',
      '@Files' => '',
      '@Created date' => '',
      '@Path' => '',
    ];

    foreach ($data as $row) {
      $columnHeaders = array_keys($row);
      foreach ($linkHeaders as $linkHeader) {
        if (!in_array($linkHeader, $columnHeaders)) {
          $missing['@' . $linkHeader] = $this->t('<li> @column </li>', ['@column' => $linkHeader]);
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
    $error_matrix = [];

    $errors = [
      'title' => $this->t('The Title field is required.'),
      'file' => $this->t('File URL is invalid.'),
      'missing_url' => $this->t('The URL field is required.'),
      'invalid_url' => $this->t('URL is invalid.'),
    ];

    $message = [
      '@title' => '',
      '@file' => '',
      '@missing_url' => '',
      '@invalid_url' => '',
    ];

    // Check common validations.
    $msg_count = 0;
    $parent_msg = parent::validateRows($data);
    if ($parent_msg) {
      $message = array_merge($message, $parent_msg);
      $msg_count += $message['count'];
      unset($message['count']);
    }

    foreach ($data as $delta => $row) {
      $row_number = ++$delta;

      // Title is required.
      if (empty($row['Title'])) {
        $error_matrix['title'][] = $row_number;
      }

      // Validate File url.
      if ($url = $row['Files']) {
        $headers = get_headers($url, 1);
        if (strpos($headers[0], '200') === FALSE) {
          $error_matrix['file'][] = $row_number;
        }
      }

      // URL is required.
      if (empty($row['URL'])) {
        $error_matrix['missing_url'][] = $row_number;
      }

      // Validate URL.
      if (!empty($row['URL']) && !UrlHelper::isValid($row['URL'], TRUE)) {
        $error_matrix['invalid_url'][] = $row_number;
      }
    }

    foreach ($error_matrix as $placeholder => $rows) {
      if (empty($rows)) {
        continue;
      }

      $msg_arr = $this->getErrorMessage(implode(',', $rows), $errors[$placeholder]);
      $message['@' . $placeholder] = $msg_arr['message'];
      $msg_count += $msg_arr['count'];
    }

    if ($msg_count > 0) {
      $message['@summary'] = $this->t('The Import file has @count error(s).', ['@count' => $msg_count]);
    }

    return $msg_count ? $message : [];
  }

  /**
   * {@inheritdoc}
   */
  public function migratePostRowSaveActions(MigratePostRowSaveEvent $event) {
    $row = $event->getRow();
    $id = (int) $event->getDestinationIdValues()[0];
    $source = $row->getSource();

    $vocabs = array_diff(array_keys($source), $this->sourceKeys);
    if ($vocabs) {
      $this->addTerms($vocabs, $source, $id);
    }
    $this->cpImportHelper->addContentToVsite($id, $this->groupPluginId, 'node');
  }

}
