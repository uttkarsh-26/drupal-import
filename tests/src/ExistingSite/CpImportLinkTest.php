<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Core\File\FileSystemInterface;
use Drupal\cp_import\AppImport\os_link_import\AppImport;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpImportLinkTest.
 *
 * @group kernel
 * @group cp-1
 */
class CpImportLinkTest extends OsExistingSiteTestBase {

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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->vsiteContextManager->activateVsite($this->group);
    $this->cpImportHelper = $this->container->get('cp_import.helper');
    $this->migrationManager = $this->container->get('plugin.manager.migration');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->fileSystem = $this->container->get('file_system');
    $this->groupMember = $this->createUser();
    $this->addGroupAdmin($this->groupMember, $this->group);

    $this->drupalLogin($this->groupMember);
  }

  /**
   * Tests CpImport Link AppImport factory.
   */
  public function testCpImportLinkAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_link_import');
    $this->assertInstanceOf(AppImport::class, $instance);
  }

  /**
   * Tests CpImport News header validations.
   */
  public function testCpImportLinkHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    /** @var \Drupal\cp_import\AppImport\os_link_import\AppImport $instance */
    $instance = $appImportFactory->create('os_link_import');

    // Test header errors. Url column is missing.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'link-1',
    ];

    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@URL']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'URL' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'link-1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Link row validations.
   */
  public function testCpImportLinkRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    /** @var \Drupal\cp_import\AppImport\os_link_import\AppImport $instance */
    $instance = $appImportFactory->create('os_link_import');

    // Test Title errors.
    $data[0] = [
      'Title' => '',
      'Body' => 'Body1',
      'URL' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'link-1',
    ];

    $message = $instance->validateRows($data);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@missing_url']);

    // Test Date field errors.
    $data[0] = [
      'Title' => 'Link Title',
      'Body' => 'Body1',
      'URL' => 'invalid',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'link-1',
    ];

    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@missing_url']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@invalid_url']);

    // Test no errors in row.
    $data[0] = [
      'Title' => 'Link Title',
      'Body' => 'Body1',
      'URL' => 'https://github.com/',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'link-1',
    ];

    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check Y-m-d created date format.
    $data[0] = [
      'Title' => 'Link Title',
      'Body' => 'Body1',
      'URL' => 'https://github.com/',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '2015-01-15',
      'Path' => 'link-2',
    ];

    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check d-m-Y created date format.
    $data[0] = [
      'Title' => 'Link Title',
      'Body' => 'Body1',
      'URL' => 'https://github.com/',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '15-01-2015',
      'Path' => 'link-3',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests Migration/import for os_link_import.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCpImportMigrationLinks() {
    $filename = drupal_get_path('module', 'cp_import_csv_test') . '/artifacts/link.csv';
    $this->cpImportHelper->csvToArray($filename, 'utf-8');

    // Replace existing source file.
    $path = 'public://importcsv';
    $this->fileSystem->delete($path . '/os_link.csv');
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data(file_get_contents($filename), $path . '/os_link.csv', FileSystemInterface::EXISTS_REPLACE);

    $storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance('os_link_import');
    $migration->setStatus(MigrationInterface::STATUS_IDLE);
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $executable->rollback());

    // Test Negative case.
    $link = $storage->loadByProperties(['title' => 'Github']);
    $this->assertCount(0, $link);
    $link = $storage->loadByProperties(['title' => 'Google']);
    $this->assertCount(0, $link);

    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $executable->import());

    $this->visitViaVsite('links', $this->group);
    $this->assertSession()->pageTextContains('Links');
    $this->assertSession()->elementExists('css', '.view-links');

    // Test positive case.
    $link = $storage->loadByProperties(['title' => 'Github']);
    $this->assertCount(2, $link);
    $link = $storage->loadByProperties(['title' => 'Google']);
    $this->assertCount(1, $link);

    // Check m/d/Y date format.
    $date = date('Y-m-d', current($link)->getCreatedTime());
    $this->assertEquals($date, '2021-03-06', 'Created date are not equal');

    // Check Y-m-d date format.
    $link = $storage->loadByProperties(['title' => 'Yahoo!']);
    $this->assertCount(1, $link);
    $date = date('Y-m-d', current($link)->getCreatedTime());
    $this->assertEquals($date, '2021-03-06', 'Created date are not equal');

    $executable->rollback();
  }

}
