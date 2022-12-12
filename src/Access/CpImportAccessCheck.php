<?php

namespace Drupal\cp_import\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\os_app_access\Access\AppAccess;
use Drupal\vsite\Plugin\AppManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom access checker for CpImport.
 */
class CpImportAccessCheck implements AccessInterface, ContainerInjectionInterface {

  /**
   * App access manager.
   *
   * @var \Drupal\os_app_access\Access\AppAccess
   */
  protected $appAccess;

  /**
   * Vsite app manager.
   *
   * @var \Drupal\vsite\Plugin\AppManager
   */
  protected $vsiteAppManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new CpImportAccessCheck object.
   *
   * @param \Drupal\os_app_access\Access\AppAccess $appAccess
   *   App access instance.
   * @param \Drupal\vsite\Plugin\AppManager $vsiteAppManager
   *   Vsite app manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(AppAccess $appAccess, AppManager $vsiteAppManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->appAccess = $appAccess;
    $this->vsiteAppManager = $vsiteAppManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('os_app_access.app_access'),
      $container->get('vsite.app.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Checks whether the CpImport form is accessible to the user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $app_name
   *   The app id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $app_name): AccessResultInterface {
    $internal_path = Url::fromRoute('<current>')->getInternalPath();
    $allowed_path = [
      'cp/content/browse/news/feed',
      'cp/content/browse/blog/feed',
    ];
    if (!in_array($internal_path, $allowed_path)) {
      // Check app access.
      $access_result = $this->appAccess->access($account, $app_name);
      $appDefinition = $this->vsiteAppManager->getDefinition($app_name);
      if ($appDefinition['entityType'] == 'node') {
        foreach ($appDefinition['bundle'] as $bundle) {
          // Check if user has create access to the bundle(s) of the app.
          $entity_access_result = $this->entityTypeManager->getAccessControlHandler('node')->createAccess($bundle, NULL, [], TRUE);
        }
      }
      // For apps of custom entity type without bundles.
      else {
        $entity_access_result = $this->entityTypeManager->getAccessControlHandler($appDefinition['entityType'])->createAccess(NULL, NULL, [], TRUE);
      }
      return $access_result->orIf($entity_access_result);
    }
    elseif (AccessResult::allowedIfHasPermission($account, 'access feed overview')->isAllowed()) {
      return AccessResult::allowedIfHasPermission($account, 'access feed overview');
    }

    return AccessResult::forbidden();
  }

}
