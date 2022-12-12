<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cp_import\AppImport\os_faq_import\AppImport;
use Drupal\cp_import\AppImport\os_blog_import\AppImport as BlogAppImport;
use Drupal\cp_import\AppImport\os_software_import\AppImport as SoftwareAppImport;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\os_app_access\AppAccessLevels;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpImportTest.
 *
 * @group kernel
 * @group cp-1
 */
class CpImportTest extends OsExistingSiteTestBase {

  /**
   * CpImport helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportHelper
   */
  protected $cpImportHelper;

  /**
   * Migration manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Cp Import access checker.
   *
   * @var \Drupal\cp_import\Access\CpImportAccessCheck
   */
  protected $cpImportAccessChecker;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Group member.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $groupMember;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManagerInterface
   */
  protected $appManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->vsiteContextManager->activateVsite($this->group);
    $this->cpImportHelper = $this->container->get('cp_import.helper');
    $this->migrationManager = $this->container->get('plugin.manager.migration');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->fileSystem = $this->container->get('file_system');
    $this->appManager = $this->container->get('vsite.app.manager');
    $this->groupMember = $this->createUser();
    $this->drupalLogin($this->groupMember);
  }

  /**
   * Tests Cp import helper media creation.
   */
  public function testCpImportHelperMediaCreation() {
    // Test Media creation with File.
    $url = 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf';
    $media1 = $this->cpImportHelper->getMedia($url, 'faq', 'field_attached_media');
    $this->assertInstanceOf(Media::class, $media1);
    $this->assertEquals('dummy.pdf', $media1->getName());
    $this->markEntityForCleanup($media1);

    // Test Negative case for Media creation of Oembed type.
    $url = 'https://www.youtube.com/watch?v=WadTyp3FcgU&t';
    $media2 = $this->cpImportHelper->getMedia($url, 'faq', 'field_attached_media');
    $this->assertNull($media2);
  }

  /**
   * Tests Add to vsite.
   */
  public function testCpImportHelperAddToVsite() {
    $node = $this->createNode([
      'title' => 'Test',
      'type' => 'faq',
    ]);
    // Test No vsite.
    $vsite = $this->vsiteContextManager->getActiveVsite();
    $content = $vsite->getContentByEntityId('group_node:faq', $node->id());
    $this->assertCount(0, $content);

    // Call helper method and check again. Test vsite.
    $this->cpImportHelper->addContentToVsite($node->id(), 'group_node:faq', 'node');
    $content = $vsite->getContentByEntityId('group_node:faq', $node->id());
    $this->assertCount(1, $content);
  }

  /**
   * Tests Csv to Array conversion.
   */
  public function testCpImportHelperCsvToArray() {
    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/faq.csv';
    $data = $this->cpImportHelper->csvToArray($filename, 'utf-8');
    $this->assertCount(10, $data);
    $this->assertEquals('Some Question 5', $data[4]['Title']);
    $this->assertEquals('2014-01-20', $data[9]['Created date']);
  }

  /**
   * Tests CpImport AppImport factory.
   */
  public function testCpImportAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_faq_import');
    $this->assertInstanceOf(AppImport::class, $instance);
  }

  /**
   * Tests CpImport header validations.
   */
  public function testCpImportFaqHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_faq_import');

    // Test header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@Path']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport row validations.
   */
  public function testCpImportFaqRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_faq_import');

    // Test errors.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@date']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);

    // Test body field errors.
    $data[0] = [
      'Title' => 'Title1',
      'Body' => '',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@body']);

    // Test no errors in row.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test d-m-Y date format.
    $data[0] = [
      'Title' => 'Title1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '05-03-2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test Y-m-d date format.
    $data[0] = [
      'Title' => 'Title1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '2015-03-01',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test migration status.
    $migration = $this->migrationManager->createInstance('os_faq_import');
    $this->assertEquals(MigrationInterface::STATUS_IDLE, $migration->getStatus(), 'Migration is not idle.');
  }

  /**
   * Checks access for Faq and publication import.
   */
  public function testCpImportAccessChecker() {
    $this->cpImportAccessChecker = $this->container->get('cp_import_access.check');
    $levels = $this->configFactory->getEditable('os_app_access.access');

    // Setup role.
    $group_role = $this->createRoleForGroup($this->group);
    $group_role->grantPermissions([
      'create group_node:faq entity',
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

    $levels->set('faq', AppAccessLevels::PUBLIC)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'faq');
    $this->assertInstanceOf(AccessResultAllowed::class, $result);

    $levels->set('publications', AppAccessLevels::PUBLIC)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'publications');
    $this->assertInstanceOf(AccessResultAllowed::class, $result);

    // App access level is Disabled and user has create access.
    $levels->set('faq', AppAccessLevels::DISABLED)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'faq');
    $this->assertInstanceOf(AccessResultForbidden::class, $result);

    $levels->set('publications', AppAccessLevels::DISABLED)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'publications');
    $this->assertInstanceOf(AccessResultForbidden::class, $result);

    // Update role.
    $group_role->revokePermissions([
      'create group_node:faq entity',
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

    // App access level is Public and user does not have create access.
    $levels->set('faq', AppAccessLevels::PUBLIC)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'faq');
    $this->assertInstanceOf(AccessResultNeutral::class, $result);

    $levels->set('publications', AppAccessLevels::PUBLIC)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'publications');
    $this->assertInstanceOf(AccessResultNeutral::class, $result);

    // App access level is Disabled and user does not have create access.
    $levels->set('faq', AppAccessLevels::DISABLED)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'faq');
    $this->assertInstanceOf(AccessResultForbidden::class, $result);

    $levels->set('faq', AppAccessLevels::DISABLED)->save();
    $result = $this->cpImportAccessChecker->access($this->groupMember, 'faq');
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests Migration/import for os_faq_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationFaq() {

    $app = $this->appManager->createInstance('faq');
    $app->createVocabulary(['vocab1', 'vocab2'], 'faq');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/faq.csv';
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');
    $this->fileSystem->delete($path . '/os_faq.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_faq.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'Some Question 10']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'Some Question 2']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_faq_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    $node = $storage->loadByProperties(['title' => 'Some Question 1']);
    $term_names = ['term1', 'term2', 'abc', 'def'];
    $this->assertTerms(current($node), $term_names);

    // Check Y-m-d date format.
    $node1 = $storage->loadByProperties(['title' => 'Some Question 10']);
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEquals($date1, '2014-01-20', 'Created date are not equal');
    $this->assertCount(1, $node1);
    // Check m/d/Y date format.
    $node2 = $storage->loadByProperties(['title' => 'Some Question 2']);
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2014-05-01', 'Created date are not equal');
    $this->assertCount(1, $node2);

    // Check d-m-Y date format.
    $node3 = $storage->loadByProperties(['title' => 'Some Question 6']);
    $date3 = date('Y-m-d', current($node3)->getCreatedTime());
    $this->assertEquals($date3, '2020-04-02', 'Created date are not equal');
    $this->assertCount(1, $node3);

    // Test if no date was given time is not set to 0.
    $node5 = $node3 = $storage->loadByProperties(['title' => 'Some Question 5']);
    $node5 = array_values($node5)[0];
    $this->assertNotEquals(0, $node5->getCreatedTime());

    // Tests event calls helper to add content to vsite.
    $vsite = $this->vsiteContextManager->getActiveVsite();
    reset($node3);
    $id = key($node3);
    $content = $vsite->getContentByEntityId('group_node:faq', $id);
    $this->assertCount(1, $content);

    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/faq_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_faq.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_faq.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_faq_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node2 = $storage->loadByProperties(['title' => 'Some Question 2']);
    $this->assertCount(2, $node2);

    // Delete all the test data created.
    $executable->rollback();
  }

  /**
   * Tests Migration/import for os_presentations_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationPresentations() {
    $app = $this->appManager->createInstance('presentations');
    $app->createVocabulary(['vocab-present'], 'presentations');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/presentations.csv';
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');

    $this->fileSystem->delete($path . '/os_presentations.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_presentations.csv', FileSystemInterface::EXISTS_REPLACE);
    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'presentation1']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'presentation2']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_presentations_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();

    // // Check Y-m-d date format.
    $node1 = $storage->loadByProperties(['title' => 'presentation1']);
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEquals($date1, '2020-12-30', 'Created date are not equal');
    $this->assertCount(1, $node1);

    // Assert if terms are present.
    $term_names = ['pterm1', 'pterm2'];
    $this->assertTerms(current($node1), $term_names);

    // Check m/d/Y date format.
    $node2 = $storage->loadByProperties(['title' => 'presentation2']);
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2020-12-30', 'Created date are not equal');
    $this->assertCount(1, $node2);

    // Check d-m-Y date format.
    $node3 = $storage->loadByProperties(['title' => 'presentation3']);
    $date3 = date('Y-m-d', current($node3)->getCreatedTime());
    $this->assertEquals($date3, '2020-02-22', 'Created date are not equal');
    $this->assertCount(1, $node3);

    // Tests event calls helper to add content to vsite.
    $vsite = $this->vsiteContextManager->getActiveVsite();
    reset($node3);
    $id = key($node3);
    $content = $vsite->getContentByEntityId('group_node:presentation', $id);
    $this->assertCount(1, $content);

    $node6 = $storage->loadByProperties(['title' => 'presentation6']);
    reset($node6);
    $id = key($node6);
    $node6 = $storage->load($id);
    $node6_date = $node6->field_presentation_date->getValue()[0]['value'];
    $this->assertEquals('2020-06-16', $node6_date);

    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/presentations_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_presentations.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_presentations.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_presentations_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node3 = $storage->loadByProperties(['title' => 'presentation2']);
    $this->assertCount(2, $node3);

    // Delete all the test data created.
    $executable->rollback();
  }

  /**
   * Tests CpImport Blog AppImport factory.
   */
  public function testCpImportBlogAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_blog_import');
    $this->assertInstanceOf(BlogAppImport::class, $instance);
  }

  /**
   * Tests CpImport Blog header validations.
   */
  public function testCpImportBlogHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_blog_import');

    // Test header errors.
    $data[0] = [
      'Title' => 'Blog1',
      'Body' => 'Blog1 Test Body',
      'Files' => '',
      'Created date' => '01/01/2015',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@Path']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Blog2',
      'Body' => 'Body2 Test Body',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Blog row validations.
   */
  public function testCpImportBlogRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_blog_import');

    // Test Single Row error.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@date']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);
    $this->assertStringContainsStringIgnoringCase('Row 1: The Title is required.', $message['@title']->__toString());
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@summary']);
    $this->assertStringContainsStringIgnoringCase('The Import file has 1 error(s).', $message['@summary']->__toString());

    // Test Multiple error messages.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $data[1] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '33/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);
    $this->assertStringContainsString('<a data-toggle="tooltip" title="Rows: 1,2">2 Rows</a>: The Title is required.', $message['@title']->__toString());
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@date']);
    $this->assertStringContainsString('Row 2: Created date format is invalid.', $message['@date']->__toString());
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@summary']);
    $this->assertStringContainsString('The Import file has 3 error(s).', $message['@summary']->__toString());

    // Test no errors in row.
    $data = [];
    $data[0] = [
      'Title' => 'Blog1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test d-m-Y date format.
    $data[0] = [
      'Title' => 'Blog1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '05-03-2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test Y-m-d date format.
    $data[0] = [
      'Title' => 'Blog1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '2015-03-01',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test migration status.
    $migration = $this->migrationManager->createInstance('os_blog_import');
    $this->assertEqual(MigrationInterface::STATUS_IDLE, $migration->getStatus(), 'Migration is not idle.');
  }

  /**
   * Tests Migration/import for os_blog_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationBlog() {

    $app = $this->appManager->createInstance('blog');
    $app->createVocabulary(['vocab-blog'], 'blog');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/blog.csv';
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');
    $this->fileSystem->delete($path . '/os_blog.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_blog.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'Test Blog 1']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'Test Blog 8']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_blog_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();

    // Test positive case.
    $node1 = $storage->loadByProperties(['title' => 'Test Blog 1']);
    $this->assertCount(1, $node1);
    // Check created date m/d/Y format.
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEquals($date1, '2020-12-31', 'Created date are not equal');

    // Assert if terms are present.
    $term_names = ['bterm1', 'bterm2'];
    $this->assertTerms(current($node1), $term_names);

    // Check created date Y-m-d format.
    $node2 = $storage->loadByProperties(['title' => 'Test Blog 2']);
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2020-04-01', 'Created date are not equal');

    // Check created date d-m-Y format.
    $node3 = $storage->loadByProperties(['title' => 'Test Blog 3']);
    $date2 = date('Y-m-d', current($node3)->getCreatedTime());
    $this->assertEquals($date2, '2020-03-05', 'Created date are not equal');
    $this->assertCount(1, $node3);

    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/blog_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_blog.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_blog.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_blog_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node1 = $storage->loadByProperties(['title' => 'Test Blog 1']);
    $this->assertCount(2, $node1);

    // Delete all the test data created.
    $executable->rollback();
  }

  /**
   * Tests CpImport Software AppImport factory.
   */
  public function testCpImportSoftwareAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_software_import');
    $this->assertInstanceOf(SoftwareAppImport::class, $instance);
  }

  /**
   * Tests CpImport Software header validations.
   */
  public function testCpImportSoftwareHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_software_import');

    // Test header errors.
    $data[0] = [
      'Title' => 'Software1',
      'Body' => 'Software1 Test Body',
      'Files' => '',
      'Created date' => '01/01/2015',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@Path']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Software1',
      'Body' => 'Body2 Test Body',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Profiles header validations.
   */
  public function testCpImportProfilesHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_profiles_import');

    // Test header errors.
    $data[0] = [
      'First Name' => '',
      'Last Name' => '',
      'Photo' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/lady6.png',
      'Email' => 'test@test.com',
      'Created date' => '01/01/2015',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertNotEmpty($message['@First name']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@Path']);

    // Test No header errors.
    $data[0] = [
      'First name' => 'First name',
      'Last name' => 'Last name',
      'Photo' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/lady6.png',
      'Created date' => '01/01/2015',
      'Email' => 'test@test.com',
      'Path' => 'test1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Profiles row validations.
   */
  public function testCpImportProfilesRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_profiles_import');

    // Test errors.
    $data[0] = [
      'First name' => '',
      'Last name' => '',
      'Photo' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/lady6.png',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
      'Email' => 'test@test.com',
      'Websites url 1' => 'ht://www.homersimpson1.com',
      'Websites url 2' => 'http://www.homersimpson2.com',
      'Websites url 3' => 'http://www.homersimpson3.com',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@date']);
    $this->assertNotEmpty($message['@website1']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@firstNameRows']);

    // Test no errors in row and check m/d/Y created date format.
    $data[0] = [
      'First name' => 'First name',
      'Last name' => 'Last name',
      'Photo' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
      'Email' => 'test@test.com',
      'Websites url 1' => 'http://www.homersimpson1.com',
      'Websites url 2' => 'http://www.homersimpson2.com',
      'Websites url 3' => 'http://www.homersimpson3.com',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
    // Check Y-m-d created date format.
    $data[0] = [
      'First name' => 'First name',
      'Last name' => 'Last name',
      'Photo' => '',
      'Created date' => '2020-03-21',
      'Path' => 'test1',
      'Email' => 'test@test.com',
      'Websites url 1' => 'http://www.homersimpson1.com',
      'Websites url 2' => 'http://www.homersimpson2.com',
      'Websites url 3' => 'http://www.homersimpson3.com',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
    // Check d-m-Y date format.
    $data[0] = [
      'First name' => 'First name',
      'Last name' => 'Last name',
      'Photo' => '',
      'Created date' => '01-03-2015',
      'Path' => 'test1',
      'Email' => 'test@test.com',
      'Websites url 1' => 'http://www.homersimpson1.com',
      'Websites url 2' => 'http://www.homersimpson2.com',
      'Websites url 3' => 'http://www.homersimpson3.com',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Software row validations.
   */
  public function testCpImportSoftwareRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_software_import');

    // Test errors.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@date']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);

    // Test no errors in row.
    $data[0] = [
      'Title' => 'Software1',
      'Body' => 'Body1',
      'Files' => '',
      'Created date' => '01/01/2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
    // Test Y-m-d date format.
    $data[0] = [
      'Title' => 'Software2',
      'Body' => 'Body2',
      'Files' => '',
      'Created date' => '2020-02-22',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
    // Test d-m-Y date format.
    $data[0] = [
      'Title' => 'Software3',
      'Body' => 'Body3',
      'Files' => '',
      'Created date' => '01-03-2015',
      'Path' => 'test1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests Migration/import for os_software_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationSoftware() {

    $app = $this->appManager->createInstance('software');
    $app->createVocabulary(['vocab-software'], 'software_project');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/software.csv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->fileSystem->delete($path . '/os_software.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_software.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'Test Software 1']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'Test Software 8']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_software_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // Test positive case.
    $node1 = $storage->loadByProperties(['title' => 'Test Software 1']);
    $this->assertCount(1, $node1);
    // Check m/d/Y date format.
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEquals($date1, '2014-01-05', 'Created date are not equal');

    // Assert if terms are present.
    $term_names = ['sterm1', 'sterm2'];
    $this->assertTerms(current($node1), $term_names);

    $node2 = $storage->loadByProperties(['title' => 'Test Software 3']);
    $this->assertCount(1, $node2);
    // Check Y-m-d date format.
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2020-02-22', 'Created date are not equal');
    // Test date is converted from Y-n-j to Y-m-d if node is created
    // successfully it means conversion works.
    $node3 = $storage->loadByProperties(['title' => 'Test Software 6']);
    $node3 = array_values($node3)[0];
    $created = $node3->getCreatedTime();
    $created_date = date('Y-m-d', $created);
    $this->assertEquals('2014-01-01', $created_date);

    $node4 = $storage->loadByProperties(['title' => 'Test Software 4']);
    $this->assertCount(1, $node4);
    // Check d-m-Y date format.
    $date4 = date('Y-m-d', current($node4)->getCreatedTime());
    $this->assertEquals($date4, '2020-02-22', 'Created date are not equal');

    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/software_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_software.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_software.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_software_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node1 = $storage->loadByProperties(['title' => 'Test Software 1']);
    $this->assertCount(2, $node1);

    // Delete all the test data created.
    $executable->rollback();
  }

  /**
   * Tests Migration/import for os_profiles_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationProfiles() {

    $app = $this->appManager->createInstance('profiles');
    $app->createVocabulary(['vocab-profile'], 'person');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/profiles.csv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->fileSystem->delete($path . '/os_profiles.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_profiles.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['field_first_name' => 'Homer 1']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['field_first_name' => 'Homer 2']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_profiles_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // Test positive case.
    $node1 = $storage->loadByProperties(['field_first_name' => 'Homer 1']);
    $nodeObject = reset($node1);
    $this->assertCount(1, $node1);
    $this->assertMatchesRegularExpression('/people/', $nodeObject->toUrl()->toString());
    // Check m/d/Y date format.
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEquals($date1, '2020-12-31', 'Created date are not equal');

    // Assert that terms are present.
    $term_names = ['proterm1', 'proterm2'];
    $this->assertTerms(current($node1), $term_names);

    $node2 = $storage->loadByProperties(['field_first_name' => 'Homer 2']);
    $this->assertCount(1, $node2);
    // Check d-m-Y date format.
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2020-01-22', 'Created date are not equal');
    // Test date is converted from Y-n-j to Y-m-d if node is created.
    // successfully it means conversion works.
    $node3 = $storage->loadByProperties(['field_first_name' => 'Homer 6']);
    $node3 = array_values($node3)[0];
    $created = $node3->getCreatedTime();
    $created_date = date('Y-m-d', $created);
    $this->assertEquals('2020-01-01', $created_date);

    $node5 = $storage->loadByProperties(['field_first_name' => 'Homer 5']);
    $this->assertCount(1, $node5);
    // Check Y-m-d date format.
    $date5 = date('Y-m-d', current($node5)->getCreatedTime());
    $this->assertEquals($date5, '2020-12-31', 'Created date are not equal');
    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/profiles_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_profiles.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_profiles.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_profiles_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node1 = $storage->loadByProperties(['field_first_name' => 'Homer 1']);
    $this->assertCount(2, $node1);

    // Delete all the test data created.
    $executable->rollback();
  }

  /**
   * Tests Migration/import for os_classes_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationClasses() {

    $app = $this->appManager->createInstance('class');
    $app->createVocabulary(['vocab-class'], 'class');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/class.csv';
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');
    $this->fileSystem->delete($path . '/os_class.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_class.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'Harvard University']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'Class test']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_classes_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();

    // Check Y-m-d date format.
    $node1 = $storage->loadByProperties(['title' => 'Harvard University']);
    $this->assertCount(1, $node1);
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertNotEquals($date1, '2014-01-20', 'Created date are not equal');

    // Assert that terms are present.
    $term_names = ['cterm1', 'cterm2'];
    $this->assertTerms(current($node1), $term_names);

    // Check correct m/d/Y date format.
    $node2 = $storage->loadByProperties(['title' => 'Class test']);
    $this->assertCount(1, $node2);
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEquals($date2, '2020-11-30', 'Created date is equal');

    // Add content to vsite.
    $vsite = $this->vsiteContextManager->getActiveVsite();
    reset($node2);
    $id = key($node2);
    $content = $vsite->getContentByEntityId('group_node:class', $id);
    $this->assertCount(1, $content);

    // Delete all the test data created.
    $executable->rollback();
  }

}
