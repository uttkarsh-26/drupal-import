<?php

namespace Drupal\cp_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Creates responses for CP settings routes.
 *
 * Inspired from SystemController.
 *
 * @see \Drupal\system\Controller\SystemController
 */
class CpImportController extends ControllerBase {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(MigrationPluginManager $manager) {
    $this->migrationManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * Download Import sample template.
   */
  public function downloadTemplate($app_name = NULL) {
    $headers = [
      'Content-Type' => 'text/csv; charset=utf-8',
      'Cache-Control' => 'max-age=60, must-revalidate',
      'Content-Description' => 'File Download',
      'Content-Disposition' => 'attachment; filename=' . "$app_name.csv",
    ];

    $app_name = ($app_name === 'os_links') ? 'links' : $app_name;
    $uri = drupal_get_path('module', 'cp_import') . '/import_templates/os_' . $app_name . '.csv';
    // Return and trigger file donwload.
    return new BinaryFileResponse($uri, 200, $headers, TRUE);
  }

  /**
   * Download sample ical template.
   */
  public function downloadIcalTemplate($app_name = NULL) {
    $headers = [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Cache-Control' => 'max-age=60, must-revalidate',
      'Content-Description' => 'File Download',
      'Content-Disposition' => 'attachment; filename=' . "$app_name.ical",
    ];

    $uri = drupal_get_path('module', 'cp_import') . '/import_templates/os_' . $app_name . '.ical';
    // Return and trigger file donwload.
    return new BinaryFileResponse($uri, 200, $headers, TRUE);
  }

}
