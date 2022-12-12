<?php

namespace Drupal\cp_import;

use Drupal\Core\Language\LanguageManager;
use Drupal\cp_import\AppImport\BaseInterface;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\vsite\Path\VsiteAliasRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;

/**
 * AppImportFactory class acts as a bridge between events subscriber and apps.
 *
 * To generate respective app instance for invoking methods for those apps.
 */
final class AppImportFactory {

  /**
   * Cp Import helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportHelper
   */
  protected $cpImportHelper;

  /**
   * Vsite Alias Repository service.
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
   * AppImportFactory constructor.
   *
   * @param \Drupal\cp_import\Helper\CpImportHelper $cpImportHelper
   *   CpImportHelper instance.
   * @param \Drupal\vsite\Path\VsiteAliasRepository $vsiteAliasRepository
   *   Vsite alias repository instance.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   Language manager instance.
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

  }

  /**
   * Creates a new app importing class.
   *
   * @param string $app_import_type
   *   The app import type.
   *
   * @return \Drupal\cp_import\AppImport\BaseInterface|null
   *   The AppImport class.
   */
  public function create($app_import_type) : ?BaseInterface {
    $app_class = "Drupal\\cp_import\\AppImport\\$app_import_type\\AppImport";

    if (class_exists($app_class)) {
      return new $app_class($this->cpImportHelper, $this->vsiteAliasRepository, $this->languageManager, $this->entityTypeManager, $this->vsiteContextManager);
    }

    return NULL;
  }

}
