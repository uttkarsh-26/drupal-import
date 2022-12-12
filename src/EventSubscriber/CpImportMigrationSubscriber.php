<?php

namespace Drupal\cp_import\EventSubscriber;

use Drupal\cp_import\AppImportFactory;
use Drupal\migrate_plus\Event\MigrateEvents as MigrateEventsPlus;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class to subscribe to various migration events and perform some alteration.
 *
 * Accordingly such as creating media.
 */
class CpImportMigrationSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * App Import Factory.
   *
   * @var \Drupal\cp_import\AppImportFactory
   */
  protected $appImportFactory;

  /**
   * PreRowSaveMigrationSubscriber constructor.
   *
   * @param \Drupal\cp_import\AppImportFactory $appImportFactory
   *   AppImportFactory instance.
   */
  public function __construct(AppImportFactory $appImportFactory) {
    $this->appImportFactory = $appImportFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_ROW_SAVE][] = ['onMigratePreRowSave'];
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onMigratePostRowSave'];
    $events[MigrateEventsPlus::PREPARE_ROW][] = ['onPrepareRow'];
    return $events;
  }

  /**
   * Create Media row wise and attach it to the node entity being created.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The import event object.
   */
  public function onMigratePreRowSave(MigratePreRowSaveEvent $event) {
    $migrationId = $event->getMigration()->getBaseId();
    /** @var \Drupal\cp_import\AppImport\BaseInterface $instance */
    $instance = $this->appImportFactory->create($migrationId);

    if ($instance) {
      $instance->preRowSaveActions($event);
    }
  }

  /**
   * Attach the created entity to the corresponding vsite. This should happen.
   *
   * Automatically as hooks for the same are in place.
   *
   * But somehow is not happening so we need to do it explicitly.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Them import event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $migrationId = $event->getMigration()->getBaseId();
    /** @var \Drupal\cp_import\AppImport\BaseInterface $instance */
    $instance = $this->appImportFactory->create($migrationId);

    if ($instance) {
      $instance->migratePostRowSaveActions($event);
    }
  }

  /**
   * Validates row data and displays error messages row wise.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The migration event.
   *
   * @throws \Exception
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $migrationId = $event->getMigration()->getBaseId();
    /** @var \Drupal\cp_import\AppImport\BaseInterface $instance */
    $instance = $this->appImportFactory->create($migrationId);

    if ($instance) {
      $instance->prepareRowActions($event);
    }
  }

}
