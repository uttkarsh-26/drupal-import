<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cp_import\AppImport\os_presentations_import\AppImport;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpImportPresentationsTest.
 *
 * @group kernel
 * @group cp-1
 */
class CpImportPresentationsTest extends OsExistingSiteTestBase {

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
    // Setup user.
    $this->groupMember = $this->createUser();
    $this->group->addMember($this->groupMember);
    // Perform tests.
    $this->drupalLogin($this->groupMember);
  }

  /**
   * Tests CpImport Presentations AppImport factory.
   */
  public function testCpImportPresentationsAppImportFactory() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_presentations_import');
    $this->assertInstanceOf(AppImport::class, $instance);
  }

  /**
   * Tests CpImport News header validations.
   */
  public function testCpImportPresentationsHeaderValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_presentations_import');

    // Test header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Body' => 'Body1',
      'Location' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message['@Title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@Date']);

    // Test No header errors.
    $data[0] = [
      'Title' => 'Test1',
      'Date' => '2015-01-01',
      'Body' => 'Body1',
      'Location' => '',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateHeaders($data);
    $this->assertEmpty($message);
  }

  /**
   * Tests CpImport Presentations row validations.
   */
  public function testCpImportPresentationsRowValidation() {
    /** @var \Drupal\cp_import\AppImportFactory $appImportFactory */
    $appImportFactory = $this->container->get('app_import_factory');
    $instance = $appImportFactory->create('os_presentations_import');

    // Test Title errors.
    $data[0] = [
      'Title' => '',
      'Date' => '2015-01-01',
      'Body' => 'Body1',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message['@presentation_date']);
    $this->assertInstanceOf(TranslatableMarkup::class, $message['@title']);

    // Test Date field.
    $data[0] = [
      'Title' => 'News Title',
      'Date' => 'Jun 16 2020',
      'Body' => 'Body1',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Test no errors in row.
    $data[0] = [
      'Title' => 'Presentations Title',
      'Date' => '2015-01-01',
      'Body' => '',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '01/15/2015',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check Y-m-d created date format.
    $data[0] = [
      'Title' => 'News Title 2',
      'Date' => '2015-01-01',
      'Body' => 'Body1',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '2020-02-22',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);

    // Check d-m-Y created date format.
    $data[0] = [
      'Title' => 'News Title 3',
      'Date' => '2015-01-01',
      'Body' => 'Body1',
      'Location' => 'Harvard',
      'Files' => 'https://raw.githubusercontent.com/openscholar/openscholar-libraries/master/test_files/dummy.pdf',
      'Created date' => '22-02-2020',
      'Path' => 'presentations-1',
    ];
    $message = $instance->validateRows($data);
    $this->assertEmpty($message);
  }

}
