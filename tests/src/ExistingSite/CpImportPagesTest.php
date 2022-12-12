<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cp_import\AppImport\os_pages_import\AppImport;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Class CpImportPagesTest.
 *
 * @group kernel
 * @group cp-1
 */
class CpImportPagesTest extends OsExistingSiteTestBase {

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
   * Book Manager.
   *
   * @var \Drupal\book\BookManager
   */
  protected $bookManager;

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
    $this->bookManager = $this->container->get('book.manager');
    // Setup user.
    $this->groupMember = $this->createUser();
    $this->group->addMember($this->groupMember);
    // Perform tests.
    $this->drupalLogin($this->groupMember);
  }

  /**
   * Tests CpImport Pages AppImport factory.
   */
  public function testCpImportPagesAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_pages_import');
    $this->assertInstanceOf(AppImport::class, $instance);
  }

  /**
   * Tests CpImport Pages header validations.
   */
  public function testCpImportPagesHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_pages_import');

    // Test header errors.
    $data[0] = [
      'Title' => 'Test page',
      'Body' => 'Body1',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'page-1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Date' => '2015-01-01',
      'Body' => 'Body1',
      'Location' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'page-1',
      'Parent Path' => '',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Pages row validations.
   */
  public function testCpImportPagesRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_pages_import');

    // Test Title errors.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'page-1',
      'Parent Path' => '',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@date']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);

    // Test no errors in row.
    $data[0] = [
      'Title' => 'Page Title',
      'Date' => '2015-01-01',
      'Body' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'page-1',
      'Parent Path' => '',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check Y-m-d created date format.
    $data[0] = [
      'Title' => 'Page Title 2',
      'Body' => 'Body1',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '2020-02-22',
      'Path' => 'page-1',
      'Parent Path' => '',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check d-m-Y created date format.
    $data[0] = [
      'Title' => 'Page Title 3',
      'Body' => 'Body1',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '22-02-2020',
      'Path' => 'page-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests Migration/import for os_pages_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationPages() {

    $appManager = $this->container->get('vsite.app.manager');
    $app = $appManager->createInstance('page');
    $app->createVocabulary(['vocab-page'], 'page');

    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/page.csv';
    // Replace existing source file.
    $path = 'public://importcsv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');

    $this->fileSystem->delete($path . '/os_page.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_page.csv', FileSystemInterface::EXISTS_REPLACE);
    $storage = $this->entityTypeManager->getStorage('node');
    // Test Negative case.
    $node1 = $storage->loadByProperties(['title' => 'Os page1']);
    $this->assertCount(0, $node1);
    $node2 = $storage->loadByProperties(['title' => 'Os page2']);
    $this->assertCount(0, $node2);

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_pages_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();

    // Check Y-m-d date format.
    $node1 = $storage->loadByProperties(['title' => 'Os page1']);
    $date1 = date('Y-m-d', current($node1)->getCreatedTime());
    $this->assertEqual($date1, '2020-12-30', 'Created date are not equal');
    $this->assertCount(1, $node1);
    // Assert if terms are present.
    $term_names = ['pageterm1', 'pageterm2'];
    $this->assertTerms(current($node1), $term_names);

    // Check m/d/Y date format.
    $node2 = $storage->loadByProperties(['title' => 'Os page2']);
    $date2 = date('Y-m-d', current($node2)->getCreatedTime());
    $this->assertEqual($date2, '2020-12-30', 'Created date are not equal');
    $this->assertCount(1, $node2);

    // Check d-m-Y date format.
    $node3 = $storage->loadByProperties(['title' => 'Os page3']);
    $date3 = date('Y-m-d', current($node3)->getCreatedTime());
    $this->assertEqual($date3, '2020-02-22', 'Created date are not equal');
    $this->assertCount(1, $node3);

    // Test Page with valid Parent Path.
    $node6 = $storage->loadByProperties(['title' => 'Os page6']);
    reset($node6);
    $id6 = key($node6);
    // Test Page with invalid Parent Path.
    $node7 = $storage->loadByProperties(['title' => 'Os page7']);
    $this->assertCount(1, $node7);
    $id7 = key($node7);
    $node7 = $storage->load($id7);
    // Load book created Os page6.
    $book = $this->bookManager->loadBookLink($id6, FALSE);
    $this->assertEquals($id6, $book['nid']);
    // Child book page.
    $this->assertEquals($book['nid'], $node7->book['bid']);
    $this->assertEquals($book['nid'], $node7->book['pid']);
    // Second book page.
    $node8 = $storage->loadByProperties(['title' => 'Os page8']);
    $this->assertCount(1, $node8);
    reset($node8);
    $id8 = key($node8);
    $node8 = $storage->load($id8);
    $this->assertEquals($book['nid'], $node8->book['bid']);
    $this->assertEquals($id7, $node8->book['pid']);
    // Tests event calls helper to add content to vsite.
    $vsite = $this->vsiteContextManager->getActiveVsite();
    reset($node3);
    $id = key($node3);
    $content = $vsite->getContentByEntityId('group_node:page', $id);
    $this->assertCount(1, $content);

    // Import same content again to check if adding timestamp works.
    $filename2 = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/page_small.csv';
    $this->cpImportHelper->csvToArray($filename2, 'utf-8');
    $this->fileSystem->delete($path . '/os_page.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename2), $path . '/os_page.csv', FileSystemInterface::EXISTS_REPLACE);
    $migration = $this->migrationManager->createInstance('os_pages_import');
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    // If count is now 2 it means same content is imported again.
    $node3 = $storage->loadByProperties(['title' => 'Os page2']);
    $this->assertCount(2, $node3);

    // Delete all the test data created.
    $executable->rollback();
  }

}
