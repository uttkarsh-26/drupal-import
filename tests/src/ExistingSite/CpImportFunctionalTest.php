<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\os_app_access\AppAccessLevels;
use Drupal\Tests\cp_users\Traits\CpUsersTestTrait;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * CpImportJsTest.
 *
 * @group functional
 * @group cp-import
 */
class CpImportFunctionalTest extends OsExistingSiteTestBase {

  use CpUsersTestTrait;

  /**
   * Group administrator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupMember;

  /**
   * AppAccess level.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $levels;

  /**
   * Group role.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected $groupRole;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->groupMember = $this->createUser();
    $this->group = $this->createGroup([
      'path' => [
        'alias' => '/test-alias',
      ],
    ]);
    $this->vsiteContextManager->activateVsite($this->group);

    $this->levels = $this->configFactory->getEditable('os_app_access.access');
  }

  /**
   * Test Faq import form and sample download.
   */
  public function testFaqImportForm() {
    $this->levels->set('faq', AppAccessLevels::PUBLIC)->save();

    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_node:faq entity',
    ])->save();

    // Setup user.
    $member = $this->createUser();
    $this->group->addMember($member, [
      'group_roles' => [
        $group_role->id(),
      ],
    ]);

    // Perform tests.
    $this->drupalLogin($member);

    $this->visitViaVsite('cp/content/import/faq', $this->group);
    $session = $this->assertSession();
    // Checks Faq import form opens.
    $session->pageTextContains('FAQ');
    // Check sample download link.
    $url = "/test-alias/cp/content/import/faq/template";
    $session->linkByHrefExists($url);

    // Test sample download link.
    $this->drupalGet('test-alias/cp/content/import/faq/template');
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv; charset=utf-8');
    $this->assertSession()->responseHeaderContains('Content-Description', 'File Download');
  }

  /**
   * Test Software import form and sample download.
   */
  public function testSoftwareImportForm() {
    $this->levels->set('software', AppAccessLevels::PUBLIC)->save();

    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_node:software_release entity',
      'create group_node:software_project entity',
    ])->save();

    // Setup user.
    $member = $this->createUser();
    $this->group->addMember($member, [
      'group_roles' => [
        $group_role->id(),
      ],
    ]);

    // Perform tests.
    $this->drupalLogin($member);

    $this->visitViaVsite('cp/content/import/software', $this->group);
    $session = $this->assertSession();
    // Checks Software import form opens.
    $session->pageTextContains('Software');
    // Check sample download link.
    $url = "/test-alias/cp/content/import/software/template";
    $session->linkByHrefExists($url);

    // Test sample download link.
    $this->drupalGet('test-alias/cp/content/import/software/template');
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv; charset=utf-8');
    $this->assertSession()->responseHeaderContains('Content-Description', 'File Download');
  }

  /**
   * Test Profile import form and sample download.
   */
  public function testProfilesImportForm() {
    $this->levels->set('profiles', AppAccessLevels::PUBLIC)->save();

    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_node:person entity',
    ])->save();

    // Setup user.
    $member = $this->createUser();
    $this->group->addMember($member, [
      'group_roles' => [
        $group_role->id(),
      ],
    ]);

    // Perform tests.
    $this->drupalLogin($member);

    $this->visitViaVsite('cp/content/import/profiles', $this->group);
    $session = $this->assertSession();
    // Checks Profile import form opens.
    $session->pageTextContains('Person');
    // Check sample download link.
    $url = "/test-alias/cp/content/import/profiles/template";
    $session->linkByHrefExists($url);

    // Test sample download link.
    $this->drupalGet('test-alias/cp/content/import/profiles/template');
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv; charset=utf-8');
    $this->assertSession()->responseHeaderContains('Content-Description', 'File Download');
  }

  /**
   * Test Publication import form.
   */
  public function testPublicationImportForm() {
    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_entity:bibcite_reference entity',
    ])->save();

    // Setup user.
    $member = $this->createUser();
    $this->group->addMember($member, [
      'group_roles' => [
        $group_role->id(),
      ],
    ]);

    // Perform tests.
    $this->drupalLogin($member);

    $this->visitViaVsite('cp/content/import/publications', $this->group);
    $session = $this->assertSession();
    // Checks Publication import form opens.
    $session->pageTextContains('Publication');
    // Check if description is as per publication import.
    $session->pageTextContains('Import files with more than 100 entries are not permitted. Try creating multiple import files in 100 entry increments');
    // Check Publication format field exists.
    $session->fieldExists('format');
  }

  /**
   * Checks access for Faq import.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testFaqImportPermission() {
    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_node:faq entity',
    ])->save();

    // Setup user.
    $member = $this->createUser();
    $this->group->addMember($member, [
      'group_roles' => [
        $group_role->id(),
      ],
    ]);

    // Perform tests.
    $this->drupalLogin($member);

    $this->visitViaVsite('cp/content/import/faq', $this->group);
    $session = $this->assertSession();
    // Checks Faq import Access is allowed.
    $session->statusCodeEquals(200);

    $this->levels->set('faq', AppAccessLevels::DISABLED)->save();
    $this->visitViaVsite('cp/content/import/faq', $this->group);
    // Checks access is not allowed.
    $session = $this->assertSession();
    $session->statusCodeEquals(403);
  }

  /**
   * Set the app access to public again for other tests to follow.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function tearDown(): void {
    $this->levels->set('faq', AppAccessLevels::PUBLIC)->save();
    parent::tearDown();
  }

}
