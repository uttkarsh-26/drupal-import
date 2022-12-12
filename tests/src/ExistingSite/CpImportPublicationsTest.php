<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpImportPublicationsTest.
 *
 * @group kernel
 * @group cp-1
 *
 * @coversDefaultClass \Drupal\cp_import\Helper\CpImportPublicationHelper
 */
class CpImportPublicationsTest extends OsExistingSiteTestBase {

  /**
   * CpImport helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportPublicationHelper
   */
  protected $cpImportHelper;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->vsiteContextManager->activateVsite($this->group);
    $this->cpImportHelper = $this->container->get('cp_import.publication_helper');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests Saving a Bibtex entry works.
   *
   * @covers ::savePublicationEntity
   * @covers ::mapPublicationHtmlFields
   */
  public function testCpImportHelperSavePublicationBibtex() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication One';

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    // Prepare data entry array.
    $abstract = 'This paper presents measurements of spectrally';
    $entry = [
      'type' => 'article',
      'journal' => 'Proceeding of the Combustion Institute',
      'title' => $title,
      'volume' => '32',
      'year' => '2009',
      'pages' => '963-970',
      'chapter' => '963',
      'abstract' => $abstract,
      'author' => ['F. Goulay', 'L. Nemes'],
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'bibtex');

    $pubArr = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $title,
    ]);

    // Test Positive.
    // Assert Saving Bibtex entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $this->assertEquals($title, $pubEntity->get('title')->getValue()[0]['value']);
    $this->assertEquals(32, $pubEntity->get('bibcite_volume')->getValue()[0]['value']);

    // Test Mapping worked.
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals($abstract, $pubEntity->get('html_abstract')->getValue()[0]['value']);
  }

  /**
   * Tests invalid year values.
   *
   * @covers ::preValidateRows
   * @covers ::validateYear
   */
  public function testCpImportHelperInvalidYearValues() {
    $invalid_values = [
      '',
      '111/2010',
      '13/2000',
      '11/222/2010',
      '11/05/20101',
      '2/32/2000',
      'AnyOtherWord',
    // Year earlier than 1000 is invalid.
      '999',
    ];
    $entries = [];
    foreach ($invalid_values as $value) {
      // Prepare data entry array.
      $entries[] = [
        'type' => 'article',
        'title' => $this->randomMachineName(),
        'year' => $value,
      ];
    }
    $errors = $this->cpImportHelper->preValidateRows($entries, 'bibtex');
    $this->assertNotEmpty($errors['year_errors']);
    $this->assertCount(count($invalid_values), $errors['year_errors']);
  }

  /**
   * Tests valid year values.
   *
   * @covers ::preValidateRows
   * @covers ::validateYear
   */
  public function testCpImportHelperValidYearValues() {
    $valid_values = [
      '1000',
      '2010',
      '11/2010',
      '11/22/2010',
      '2/2000',
      '2/25/2000',
      '2/2/2000',
    // Leap year.
      '2/29/2020',
      'Forthcoming',
      'Submitted',
      'In Preparation',
      'In Press',
      'Working Paper',
    ];
    $entries = [];
    foreach ($valid_values as $value) {
      // Prepare data entry array.
      $entries[] = [
        'type' => 'article',
        'title' => $this->randomMachineName(),
        'year' => $value,
      ];
    }
    $errors = $this->cpImportHelper->preValidateRows($entries, 'bibtex');
    $this->assertEmpty($errors['year_errors']);
  }

  /**
   * Test saving day, month and year values.
   *
   * @covers ::savePublicationEntity
   */
  public function testCpImportHelperSavingDateValues() {
    $format_id = 'bibtex';
    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    // Only year.
    $entry = [
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'year' => '2010',
    ];
    $context = $this->cpImportHelper->savePublicationEntity($entry, $format_id);
    $publications = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $entry['title'],
    ]);
    // Assert Saving Bibtex entry with year.
    $this->assertNotEmpty($publications);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $publication */
    $publication = array_shift($publications);
    $this->markEntityForCleanup($publication);
    $saved_year = $publication->get('bibcite_year')->getString();
    $this->assertEquals(2010, $saved_year);

    // Month and year.
    $entry = [
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'year' => '11/2010',
    ];
    $context = $this->cpImportHelper->savePublicationEntity($entry, $format_id);
    $publications = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $entry['title'],
    ]);
    // Assert Saving Bibtex entry with year.
    $this->assertNotEmpty($publications);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $publication */
    $publication = array_shift($publications);
    $this->markEntityForCleanup($publication);
    $saved_year = $publication->get('bibcite_year')->getString();
    $this->assertEquals(2010, $saved_year);
    $publication_month = $publication->get('publication_month')->getString();
    $this->assertEquals(11, $publication_month);

    // Day, Month and year.
    $entry = [
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'year' => '11/26/2010',
    ];
    $context = $this->cpImportHelper->savePublicationEntity($entry, $format_id);
    $publications = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $entry['title'],
    ]);
    // Assert Saving Bibtex entry with year.
    $this->assertNotEmpty($publications);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $publication */
    $publication = array_shift($publications);
    $this->markEntityForCleanup($publication);
    $saved_year = $publication->get('bibcite_year')->getString();
    $this->assertEquals(2010, $saved_year);
    $publication_month = $publication->get('publication_month')->getString();
    $this->assertEquals(11, $publication_month);
    $publication_day = $publication->get('publication_day')->getString();
    $this->assertEquals(26, $publication_day);
  }

  /**
   * Tests empty title value.
   *
   * @covers ::preValidateRows
   */
  public function testCpImportHelperEmptyTitle() {
    // Prepare data entry array.
    $entries[] = [
      'type' => 'article',
      'title' => '',
      'year' => '2010',
    ];
    $errors = $this->cpImportHelper->preValidateRows($entries, 'bibtex');
    $this->assertNotEmpty($errors['title_errors']);
    $this->assertCount(1, $errors['title_errors']);
    // Prepare data entry array.
    $pubmed_entries[] = [
      'type' => 'article',
      'ArticleTitle' => '',
      'Year' => '2010',
    ];
    $errors = $this->cpImportHelper->preValidateRows($pubmed_entries, 'pubmed');
    $this->assertNotEmpty($errors['title_errors']);
    $this->assertCount(1, $errors['title_errors']);
    $errors = $this->cpImportHelper->preValidateRows($pubmed_entries, 'pubmed_id_list');
    $this->assertNotEmpty($errors['title_errors']);
    $this->assertCount(1, $errors['title_errors']);
  }

  /**
   * Tests Saving a Bibtex entry works with year as string and correct mapping.
   *
   * @covers ::savePublicationEntity
   * @covers ::mapPublicationHtmlFields
   */
  public function testCpImportHelperSavePublicationBibtexCodedYear() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication Two';

    // Prepare data entry array.
    $abstract = 'This paper presents measurements of spectrally';
    $entry = [
      'type' => 'article',
      'journal' => 'Proceeding of the Combustion Institute',
      'title' => $title,
      'volume' => '32',
      'year' => 'In Press',
      'pages' => '963-970',
      'chapter' => '963',
      'abstract' => $abstract,
      'author' => ['M. Nind', 'L. Find'],
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'bibtex');

    $pubArr = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $title,
    ]);

    // Assert Saving Bibtex entry with string year worked.
    $this->assertNotEmpty($pubArr, "Unable to load journal article [" . $title . "]");
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values.
    $this->assertEquals($title, $pubEntity->get('title')->getValue()[0]['value']);
    // Test year is mapped correctly.
    $this->assertEquals(10030, $pubEntity->get('bibcite_year')->getValue()[0]['value']);
  }

  /**
   * Tests Special character mapping works.
   *
   * @covers ::mapSpecialChars
   */
  public function testCpImportHelperMapSpecialChars() {

    $entry = [
      // Test some random symbols.
      'symbols' => '$\#$ \%\&nbsp;\&amp;\&nbsp; + - ( )\&nbsp; * \&amp; ^ \%$ $\#$ @ !\&nbsp;\&nbsp; {\~A}',
      // Test some random text and symbol combination.
      'texts' => '{\textyen} {\~A} {\textregistered} "paper" {\textquoteright}presents{\textquoteright} {\textquoteleft}measurements{\textquoteleft}',
    ];

    // &nbsp appearing is ok as it will be read and handled by the browser not
    // our mapper.
    $expectedSymbols = '# %&nbsp;&amp;&nbsp; + - ( )&nbsp; * &amp; ^ %$ # @ !&nbsp;&nbsp; Ã';
    $expectedTextSymbols = '¥ Ã ® "paper" ’presents’ ‘measurements‘';

    $this->cpImportHelper->mapSpecialChars($entry);

    $this->assertEquals($expectedSymbols, $entry['symbols']);
    $this->assertEquals($expectedTextSymbols, $entry['texts']);
  }

  /**
   * Tests Editors are saved correctly and url mapping.
   *
   * @covers ::saveEditors
   * @covers ::savePublishersVersionUrl
   */
  public function testCpImportHelperSaveAuthorsEditors() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication Three';
    // Prepare data entry array.
    $abstract = 'This paper presents measurements of spectrally';
    $entry = [
      'type' => 'article',
      'journal' => 'Proceeding of the Combustion Institute',
      'title' => $title,
      'volume' => '32',
      'year' => '2009',
      'pages' => '963-970',
      'chapter' => '963',
      'url' => 'http://abcde.net/thisarticle',
      'abstract' => $abstract,
      'author' => ['F. Goulay', 'L. Nemes'],
      // Editor not processed by the decoder so they will
      // be passed as a string for further processing.
      'editor' => 'Editor One and Editor Two',
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'bibtex');

    $pubArr = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $title,
    ]);

    // Assert Saving Bibtex entry with editors worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values.
    $this->assertEquals($title, $pubEntity->get('title')->getValue()[0]['value']);
    // Test editors are saved correctly.
    $contributors = $pubEntity->get('author')->getValue();
    $this->assertCount(4, $contributors);
    $this->assertEquals('editor', $contributors[2]['role']);
    $this->assertEquals('primary', $contributors[3]['category']);

    // Test url is saved correctly.
    $urlField = $pubEntity->get('publishers_version')->getValue()[0];
    $this->assertNotEmpty($urlField['title']);
    $this->assertEquals('http://abcde.net/thisarticle', $urlField['uri']);
  }

  /**
   * Tests Saving a Pubmed entry works.
   *
   * @covers ::savePublicationEntity
   * @covers ::mapPublicationHtmlFields
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function testCpImportHelperSavePublicationPubmedXml() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication Four';

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    // Prepare data entry array.
    $abstract = 'This is a journal article pubmed test.';
    $authors = [
      [
        'name' => "Jan L Brozek",
        'category' => 'primary',
      ],
      [
        'name' => "Monica Kraft",
        'category' => 'primary',
      ],
    ];
    $entry = [
      'ArticleTitle' => $title,
      'PublicationType' => "JOURNAL ARTICLE",
      'AuthorList' => $authors,
      'Volume' => '32',
      'Year' => '2009',
      'Pagination' => '963-970',
      'PMID' => '22928176',
      'Abstract' => $abstract,
      'url' => "https://www.ncbi.nlm.nih.gov/pubmed/22928176",
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'pubmed');

    $this->assertFalse(isset($context['errors']));

    $pubArr = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $title,
    ]);

    // Test Positive.
    // Assert Saving Pubmed entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $this->assertEquals('22928176', $pubEntity->get('bibcite_pmid')->getValue()[0]['value']);
    $this->assertEquals('2009', $pubEntity->get('bibcite_year')->getValue()[0]['value']);

    // Test Mapping worked.
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals($abstract, $pubEntity->get('html_abstract')->getValue()[0]['value']);
  }

  /**
   * Tests Saving a Endnote Tagged entry works.
   *
   * @covers ::savePublicationEntity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function testCpImportHelperSavePublicationEndNoteTagged() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication Tagged five';

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    $entry = [
      'title' => $title,
      'type' => 'Conference Paper',
      'secondary title' => 'ConferenceName',
      'year' => '2009',
      'publisher' => 'ABC Printing Co',
      'place published' => 'place published',
      'date' => 'Dec 25',
      'lang' => 'eng',
      'authors' => ['M. Nind', 'L. Find'],
      'editor' => ['K. Bayer', 'K. John'],
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'tagged');

    $this->assertFalse(isset($context['errors']));

    $pubArr = $storage->loadByProperties([
      'type' => 'conference_paper',
      'title' => $title,
    ]);

    // Test Positive.
    // Assert Saving Pubmed entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $contributors = $pubEntity->get('author')->getValue();
    $contributor = $this->entityTypeManager->getStorage('bibcite_contributor')->load($contributors[0]['target_id']);
    $contributor2 = $this->entityTypeManager->getStorage('bibcite_contributor')->load($contributors[3]['target_id']);
    $authorName = $contributor->get('first_name')->getValue()[0]['value'] . ' ' . $contributor->get('last_name')->getValue()[0]['value'];
    $editorName = $contributor2->get('first_name')->getValue()[0]['value'] . ' ' . $contributor2->get('last_name')->getValue()[0]['value'];
    $this->assertCount(4, $contributors);
    $this->assertEquals('M. Nind', $authorName);
    $this->assertEquals('K. John', $editorName);
    $this->assertEquals('editor', $contributors[2]['role']);
    $this->assertEquals('primary', $contributors[3]['category']);
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals('eng', $pubEntity->get('bibcite_lang')->getValue()[0]['value']);
    $this->assertEquals('Dec 25', $pubEntity->get('bibcite_date')->getValue()[0]['value']);
    $this->assertEquals('2009', $pubEntity->get('bibcite_year')->getValue()[0]['value']);
    $this->assertEquals('ABC Printing Co', $pubEntity->get('bibcite_publisher')->getValue()[0]['value']);
    $this->assertEquals('place published', $pubEntity->get('bibcite_place_published')->getValue()[0]['value']);
    $this->assertEquals('ConferenceName', $pubEntity->get('bibcite_secondary_title')->getValue()[0]['value']);
  }

  /**
   * Tests Saving a Endnote XML entry works.
   *
   * @covers ::savePublicationEntity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function testCpImportHelperSavePublicationEndNoteXml() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication XML 6';

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    $entry = [
      'title' => $title,
      'type' => 'Book Section',
      'secondary-title' => 'ConferenceName',
      'year' => '2009',
      'publisher' => 'Indiana University Press',
      'pub-location' => 'Bloomington',
      'language' => 'eng',
      'authors' => ['M. Nind', 'L. Find'],
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'endnote8');

    $this->assertFalse(isset($context['errors']));

    $pubArr = $storage->loadByProperties([
      'type' => 'book_chapter',
      'title' => $title,
    ]);
    // Test Positive.
    // Assert Saving Pubmed entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $contributors = $pubEntity->get('author')->getValue();
    $contributor = $this->entityTypeManager->getStorage('bibcite_contributor')->load($contributors[0]['target_id']);
    $authorName = $contributor->get('first_name')->getValue()[0]['value'] . ' ' . $contributor->get('last_name')->getValue()[0]['value'];
    $this->assertCount(2, $contributors);
    $this->assertEquals('M. Nind', $authorName);
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals('2009', $pubEntity->get('bibcite_year')->getValue()[0]['value']);
    $this->assertEquals('eng', $pubEntity->get('bibcite_lang')->getValue()[0]['value']);
    $this->assertEquals('Indiana University Press', $pubEntity->get('bibcite_publisher')->getValue()[0]['value']);
    $this->assertEquals('Bloomington', $pubEntity->get('bibcite_place_published')->getValue()[0]['value']);
    $this->assertEquals('ConferenceName', $pubEntity->get('bibcite_secondary_title')->getValue()[0]['value']);
  }

  /**
   * Tests Saving a Endnote8 XML entry.
   *
   * This tests that "issue" is saved. Also editors are saved as editor. Also
   * tests that type mapping works.
   *
   * @covers ::savePublicationEntity
   */
  public function testImportPublicationEndNote8() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = 'Test Publication XML 8';

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    $entry = [
      'title' => $title,
      'type' => 'Broadcast',
      'year' => '2009',
      'issue' => 1,
      'publisher' => 'Indiana University Press',
      'secondary-title' => 'ConferenceName',
      'editor' => ['M. Nind', 'L. Find'],
    ];

    $context = $this->cpImportHelper->savePublicationEntity($entry, 'endnote8');

    $this->assertFalse(isset($context['errors']));

    // This also asserts that type is saved as broadcast and not misc which
    // means mapping works fine.
    $pubArr = $storage->loadByProperties([
      'type' => 'broadcast',
      'title' => $title,
    ]);
    // Test Positive.
    // Assert Saving EndNote8 entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $contributors = $pubEntity->get('author')->getValue();
    $contributor = $this->entityTypeManager->getStorage('bibcite_contributor')->load($contributors[0]['target_id']);
    $authorName = $contributor->get('first_name')->getValue()[0]['value'] . ' ' . $contributor->get('last_name')->getValue()[0]['value'];
    $editor = $this->entityTypeManager->getStorage('bibcite_contributor')->load($contributors[1]['target_id']);
    $editorName = $editor->get('first_name')->getValue()[0]['value'] . ' ' . $editor->get('last_name')->getValue()[0]['value'];
    $this->assertCount(3, $contributors);
    $this->assertEquals('Not Known', $authorName);
    $this->assertEquals('M. Nind', $editorName);
    // Assert role is editor.
    $this->assertEquals('editor', $contributors[1]['role']);
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals('2009', $pubEntity->get('bibcite_year')->getValue()[0]['value']);
    $this->assertEquals('1', $pubEntity->get('bibcite_issue')->getValue()[0]['value']);
    $this->assertEquals('Indiana University Press', $pubEntity->get('bibcite_publisher')->getValue()[0]['value']);
    $this->assertEquals('ConferenceName', $pubEntity->get('bibcite_secondary_title')->getValue()[0]['value']);
  }

}
