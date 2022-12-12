<?php

namespace Drupal\cp_import\AppImport;

use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\vsite\Path\VsiteAliasRepository;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Acts as a base for AppImport factory implementation for all apps.
 *
 * @package Drupal\cp_import\AppImport
 */
abstract class Base implements BaseInterface {
  use StringTranslationTrait;

  /**
   * Cp Import helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportHelper
   */
  protected $cpImportHelper;

  /**
   * Vsite Alias Storage service.
   *
   * @var \Drupal\vsite\Path\VsiteAliasRepository
   */
  protected $vsiteAliasRepository;

  /**
   * Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Vsite Manager service.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */

  protected $vsiteManager;

  /**
   * Migrate row source keys.
   *
   * @var array
   */
  protected $sourceKeys;

  /**
   * Current Vsite.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $vsite;

  /**
   * Base constructor.
   *
   * @param \Drupal\cp_import\Helper\CpImportHelper $cpImportHelper
   *   Cp import helper instance.
   * @param \Drupal\vsite\Path\VsiteAliasRepository $vsiteAliasRepository
   *   Vsite Alias storage instance.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   Language Manager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager
   *   Vsite Context Manager service.
   */
  public function __construct(CpImportHelper $cpImportHelper, VsiteAliasRepository $vsiteAliasRepository, LanguageManager $languageManager, EntityTypeManagerInterface $entity_type_manager, VsiteContextManagerInterface $vsite_context_manager) {
    $this->cpImportHelper = $cpImportHelper;
    $this->vsiteAliasRepository = $vsiteAliasRepository;
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entity_type_manager;
    $this->vsiteContextManager = $vsite_context_manager;
    $this->vsite = $this->vsiteContextManager->getActiveVsite();
    $this->sourceKeys = [
      'path',
      'Timestamp',
      'ids',
      'header_offset',
      'fields',
      'delimiter',
      'enclosure',
      'escape',
      'plugin',
      'constants',
      'view',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRowActions(MigratePrepareRowEvent $event) {
    $source = $event->getRow()->getSource();
    $process_array = $event->getMigration()->getProcess();
    // Url patterns added for bundles.
    $patterns = $source['constants']['view'];
    $createdDate = $source['Created date'];
    if ($alias = $source['Path']) {
      if ($this->vsiteAliasRepository->lookupByAlias("$patterns" . "$alias", $this->languageManager->getDefaultLanguage()->getId())) {
        $process_array['path/pathauto'] = [
          'plugin' => 'default_value',
          'default_value' => 1,
        ];
      }
      // Disable pathauto so that user entered path from csv can be used.
      else {
        $process_array['path/pathauto'] = [
          'plugin' => 'default_value',
          'default_value' => 0,
        ];
      }
      $event->getMigration()->setProcess($process_array);
    }
    else {
      $process_array['path/pathauto'] = [
        'plugin' => 'default_value',
        'default_value' => 1,
      ];
      $event->getMigration()->setProcess($process_array);
    }
    if ($createdDate && strtotime($createdDate)) {
      $date = DrupalDateTime::createFromTimestamp(strtotime($createdDate));
      $event->getRow()->setSourceProperty('Created date', $date->format('Y-m-d'));
    }
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

  /**
   * {@inheritdoc}
   */
  public function validateRows(array $data) : array {
    $message = [];
    $dateRows = '';
    foreach ($data as $delta => $row) {
      $row_number = ++$delta;
      // Validate Date.
      if ($createdDate = $row['Created date']) {
        if (!strtotime($createdDate)) {
          $dateRows .= $row_number . ',';
        }
      }
    }
    $dateRows = rtrim($dateRows, ',');
    if ($dateRows) {
      $msg_arr = $this->getErrorMessage($dateRows, 'Created date format is invalid.');
      $message['@date'] = $msg_arr['message'];
      $message['count'] = $msg_arr['count'];
    }

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessage($rows, $message): array {
    if (strpos($rows, ',') === FALSE) {
      $message = $this->t('Row @rows: @message </br>', ['@rows' => $rows, '@message' => $message]);
      $count = 1;
    }
    else {
      $count = count(explode(',', $rows));
      $message = $this->t('<a data-toggle="tooltip" title="Rows: @rows">@count Rows</a>: @msg </br>',
        [
          '@count' => $count,
          '@rows' => $rows,
          '@msg' => $message,
        ]
      );
    }

    return ['message' => $message, 'count' => $count];
  }

  /**
   * Add terms for imported content.
   *
   * @param array $vocabs
   *   Import vocabulary columns.
   * @param array $source
   *   Row source.
   * @param string $id
   *   Node id.
   */
  public function addTerms(array $vocabs, array $source, $id) {
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = [];
    foreach ($vocabs as $vocab) {
      $vid = trim(str_replace('-', '_', $vocab));
      if ($vid && isset($source[$vocab])) {
        if (is_array($source[$vocab])) {
          $terms = $source[$vocab];
        }
        else {
          $terms = explode('|', $source[$vocab]);
        }
        foreach ($terms as $name) {
          $name = trim($name);
          if ($name) {
            $term = $term_storage->loadByProperties(['name' => $name, 'vid' => $vid]);
            if (!$term) {
              $term = $term_storage->create([
                'name' => $name,
                'vid' => $vid,
              ]);
              $term->save();
              $this->vsite->addContent($term, 'group_entity:taxonomy_term');
              $tids[] = $term->id();
            }
            else {
              $tids[] = current($term)->id();
            }
          }
        }
      }
    }
    if ($tids) {
      $node->set('field_taxonomy_terms', $tids);
    }
    $node->save();
  }

}
