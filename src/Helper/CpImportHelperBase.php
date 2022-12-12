<?php

namespace Drupal\cp_import\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\vsite\Plugin\VsiteContextManager;

/**
 * Class CpImportHelperBase.
 *
 * @package Drupal\cp_import\Helper
 */
class CpImportHelperBase implements CpImportHelperBaseInterface {

  use StringTranslationTrait;

  /**
   * Vsite Manager service.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManager
   */
  protected $vsiteManager;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CpImportHelperBase constructor.
   *
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsiteContextManager
   *   VsiteContextManager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager instance.
   */
  public function __construct(VsiteContextManager $vsiteContextManager, EntityTypeManagerInterface $entity_type_manager) {
    $this->vsiteManager = $vsiteContextManager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function addContentToVsite(string $id, $pluginId, $entityType): void {
    $vsite = $this->vsiteManager->getActiveVsite();
    // If in vsite context add content to vsite otherwise do nothing.
    if ($vsite) {
      $entity = $this->entityTypeManager->getStorage($entityType)->load($id);
      $vsite->addContent($entity, $pluginId);
    }
  }

}
