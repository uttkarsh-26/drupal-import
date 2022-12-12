<?php

namespace Drupal\cp_import\AppImport\os_events_ical_import;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\cp_import\AppImport\Base;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

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

  /**
   * {@inheritdoc}
   */
  public function prepareRowActions(MigratePrepareRowEvent $event) {
    $source = $event->getRow()->getSource();

    $signup_flag = FALSE;
    $signup_status = empty($source['registration']) ? '' : $source['registration'];
    if ($signup_status && strtolower($signup_status) == 'on') {
      $signup_flag = TRUE;
    }
    $event->getRow()->setSourceProperty('registration', $signup_flag);
    // Correct datetime to GMT with given timezone.
    $start_date = $source['dtstart'];
    if ($start_date && strtotime($start_date)) {
      $date = DrupalDateTime::createFromTimestamp(strtotime($start_date), 'GMT');
      $event->getRow()->setSourceProperty('dtstart', $date->format('Ymd\THis'));
    }
    $end_date = $source['dtend'];
    if ($end_date && strtotime($end_date)) {
      $date = DrupalDateTime::createFromTimestamp(strtotime($end_date), 'GMT');
      $event->getRow()->setSourceProperty('dtend', $date->format('Ymd\THis'));
    }
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
  public function migratePostRowSaveActions(MigratePostRowSaveEvent $event) {
    $ids = $event->getDestinationIdValues();
    foreach ($ids as $id) {
      $this->cpImportHelper->addContentToVsite($id, $this->groupPluginId, 'node');
    }
  }

}
