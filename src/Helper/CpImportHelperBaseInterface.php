<?php

namespace Drupal\cp_import\Helper;

/**
 * CpImportHelperInterface.
 */
interface CpImportHelperBaseInterface {

  /**
   * Adds the newly imported node to Vsite.
   *
   * @param string $id
   *   Entity to be added to the vsite.
   * @param string $pluginId
   *   Plugin id.
   * @param string $entityType
   *   Entity type id.
   */
  public function addContentToVsite(string $id, string $pluginId, $entityType): void;

}
