<?php

namespace Drupal\cp_import\Helper;

use Drupal\bibcite\Plugin\BibciteFormatManager;
use Drupal\bibcite_entity\Entity\Contributor;
use Drupal\bibcite_entity\Entity\Reference;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\vsite\Plugin\VsiteContextManager;
use Symfony\Component\Serializer\Serializer;
use Drupal\cp_import\CpImportLatexUnicodeMapping;

/**
 * Class CpImportPublicationHelper.
 *
 * @package Drupal\cp_import\Helper
 */
class CpImportPublicationHelper extends CpImportHelperBase {

  /**
   * BibciteFormat Manager service.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManager
   */
  protected $formatManager;

  /**
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * CpImportPublicationHelper constructor.
   *
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsiteContextManager
   *   VsiteContextManager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager instance.
   * @param \Drupal\bibcite\Plugin\BibciteFormatManager $bibciteFormatManager
   *   BibciteManager instance.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   Serializer instance.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config Factory instance.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerChannelFactory
   *   Logger channel factory instance.
   */
  public function __construct(VsiteContextManager $vsiteContextManager, EntityTypeManagerInterface $entityTypeManager, BibciteFormatManager $bibciteFormatManager, Serializer $serializer, ConfigFactory $configFactory, LoggerChannelFactory $loggerChannelFactory) {
    parent::__construct($vsiteContextManager, $entityTypeManager);
    $this->formatManager = $bibciteFormatManager;
    $this->serializer = $serializer;
    $this->configFactory = $configFactory;
    $this->logger = $loggerChannelFactory;
  }

  /**
   * Denormalize the entry and save the entity.
   *
   * @param array $entry
   *   Single entry from the import file.
   * @param string $formatId
   *   Format id.
   *
   * @return array
   *   For use in batch contexts.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function savePublicationEntity(array $entry, $formatId): array {
    switch ($formatId) {
      case 'bibtex':
        if (!array_key_exists('author', $entry) || (count($entry['author']) <= 1 && empty($entry['author'][0]))) {
          $entry['author'][0] = 'Not Known';
        }
        break;

      case 'pubmed':
      case 'pubmed_id_list':
        if (!array_key_exists('AuthorList', $entry) || count($entry['AuthorList']) == 0) {
          $entry['AuthorList'][] = [
            'name' => 'Not Known',
            'category' => 'primary',
          ];
        }
        break;

      default:
        if (!array_key_exists('authors', $entry) || (count($entry['authors']) <= 1 && empty($entry['authors'][0]))) {
          $entry['authors'][0] = 'Not Known';
        }
    }

    $result = [];
    $config = $this->configFactory->get('bibcite_import.settings');
    $denormalize_context = [
      'contributor_deduplication' => $config->get('settings.contributor_deduplication'),
      'keyword_deduplication' => $config->get('settings.keyword_deduplication'),
    ];

    $entry['year'] = $this->getYearValueFromEntry($entry, $formatId);

    // Map special characters.
    $this->mapSpecialChars($entry);

    /** @var \Drupal\bibcite_entity\Entity\Reference $entity */
    try {
      $entity = $this->serializer->denormalize($entry, Reference::class, $formatId, $denormalize_context);
      // Handle Authors.
      $authorField = $entity->get('author');
      // Handle Editors.
      if (isset($entry['editor'])) {
        $editors = $entry['editor'];
        $this->saveEditors($editors, $authorField, $formatId, $denormalize_context);
      }

      // Handle url as we need to map it to custom field.
      if (isset($entry['url']) && $url = $entry['url']) {
        $publishersVersionField = $entity->get('publishers_version');
        $this->savePublishersVersionUrl($url, $publishersVersionField);
      }
      // Handle year with month or/and day.
      $year_parts = explode('/', $entry['year']);
      if (!empty($year_parts)) {
        $year = array_pop($year_parts);
        $entity->set('bibcite_year', $year);
        // Continue with shift elements instead of pop.
        $month = array_shift($year_parts);
        if (!empty($month)) {
          $entity->set('publication_month', (int) $month);
        }
        $day = array_shift($year_parts);
        if (!empty($day)) {
          $entity->set('publication_day', (int) $day);
        }
      }
    }
    catch (\UnexpectedValueException $e) {
      // Skip import for this row.
    }

    if (!empty($entity)) {
      try {
        if ($entity->save()) {
          $result['success'] = $entity->id() . ' : ' . $entity->label();
          // Map Title and Abstract fields.
          $this->mapPublicationHtmlFields($entity);
          // Add newly saved entity to the group in context.
          $this->addContentToVsite($entity->id(), 'group_entity:bibcite_reference', $entity->getEntityTypeId());
        }
      }
      catch (\Exception $e) {
        $message = [
          $this->t('Entity can not be saved.'),
          $this->t('Label: @label', ['@label' => $entity->label()]),
          '<pre>',
          $e->getMessage(),
          '</pre>',
        ];
        $this->logger->get('bibcite_import')->error(implode("\n", $message));
        $result['error'] = $entity->label();
      }
      $result['message'] = $entity->label();
    }
    return $result;
  }

  /**
   * Map fields and save entity.
   *
   * @param \Drupal\bibcite_entity\Entity\Reference $entity
   *   Bibcite Reference entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mapPublicationHtmlFields(Reference $entity): void {
    $entity->html_title->value = $entity->title->value;
    $entity->html_abstract->value = $entity->bibcite_abst_e->value;
    // Important for abstract content to recognize html content.
    $entity->html_abstract->format = 'filtered_html';
    $entity->save();
  }

  /**
   * Maps special characters from bibtex(others) to publication.
   *
   * @param array $entry
   *   The decoded entry array.
   */
  public function mapSpecialChars(array &$entry): void {
    // Handle special chars.
    $cpImportMappingObj = new CpImportLatexUnicodeMapping();
    $searchStrings = $cpImportMappingObj->getSearchPatterns();
    $replaceStrings = $cpImportMappingObj->getReplaceStrings();
    foreach ($entry as $key => $item) {
      // Generally Author and Editor keys will be arrays so we can skip them.
      if (!is_array($item)) {
        $entry[$key] = preg_replace($searchStrings, $replaceStrings, $item);
      }
    }
  }

  /**
   * Get year value.
   *
   * @param array $entry
   *   Publication entry from import.
   * @param string $formatId
   *   Import format id.
   *
   * @return string
   *   Found year value.
   */
  public function getYearValueFromEntry(array $entry, string $formatId): string {
    switch ($formatId) {
      case 'pubmed':
      case 'pubmed_id_list':
        $year = $entry['Year'];
        break;

      default:
        $year = $entry['year'];
    }

    // To handle special cases when year is a coded string instead of a number.
    $yearMapping = $this->configFactory->get('os_publications.settings')
      ->get('publications_years_text');
    if (isset($year) && is_string($year)) {
      foreach ($yearMapping as $code => $text) {
        if (strtolower(str_replace(' ', '', $year)) === strtolower(str_replace(' ', '', $text))) {
          $year = $code;
        }
      }
    }
    return $year;
  }

  /**
   * Validate the year value.
   *
   * @param string $year
   *   Year value.
   *
   * @return bool
   *   Year valid or not.
   */
  public function validateYear(string $year): bool {
    if (empty($year)) {
      return FALSE;
    }
    // If format is DD/MM/YYYY.
    if (preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})$/', $year, $matches)) {
      try {
        new \DateTime($year);
      }
      catch (\Exception $e) {
        return FALSE;
      }
      if ($matches[1] > 12) {
        return FALSE;
      }
      return TRUE;
    }
    // If format is MM/YYYY.
    if (preg_match('/^([0-9]{1,2})\/([0-9]{4})$/', $year, $matches)) {
      if ($matches[1] > 12) {
        return FALSE;
      }
      return TRUE;
    }
    // Check if year or valid converted code.
    if (preg_match('/^([0-9]){0,5}$/', $year, $matches) && $year >= 1000) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Pre validate all rows.
   *
   * @param array $rows
   *   Uploaded rows.
   * @param string $format
   *   Selected format.
   *
   * @return array[]
   *   Affected failed rows.
   */
  public function preValidateRows(array $rows, string $format) {
    $year_errors = [];
    $title_errors = [];
    foreach ($rows as $index => $entry) {
      $row_number = ++$index;
      $year = $this->getYearValueFromEntry($entry, $format);
      if (!$this->validateYear($year)) {
        $year_errors[] = $row_number;
      }
      // Get title.
      switch ($format) {
        case 'pubmed':
        case 'pubmed_id_list':
          $title = $entry['ArticleTitle'];
          break;

        default:
          $title = $entry['title'];
      }
      if (empty($title)) {
        $title_errors[] = $row_number;
      }
    }
    return [
      'title_errors' => $title_errors,
      'year_errors' => $year_errors,
    ];
  }

  /**
   * Handle Editors. Disregarded currently by contrib module.
   *
   * @param mixed $editors
   *   The editors string.
   * @param \Drupal\Core\Field\FieldItemListInterface $authorField
   *   The field to save editors to.
   * @param string $formatId
   *   The format Id to use such as bibtex and pubmed.
   * @param array $context
   *   Settings to be used for the process.
   */
  public function saveEditors($editors, FieldItemListInterface $authorField, $formatId, array $context): void {
    // If $editors is array or not.
    if (is_string($editors)) {
      $editors = [$editors];
    }
    foreach ($editors as $editor) {
      $editor = trim($editor);
      $denormalizedEditor = $this->serializer->denormalize(['name' => [['value' => $editor]]], Contributor::class, $formatId, $context);
      // Save editor as contributor entity with proper role and category.
      $denormalizedEditor->save();
      $authorField->appendItem([
        'target_id' => $denormalizedEditor->id(),
        'category' => 'primary',
        'role' => 'editor',
      ]);
    }
  }

  /**
   * Save the url to our custom publisher's version field.
   *
   * @param string $url
   *   The url to save.
   * @param \Drupal\Core\Field\FieldItemListInterface $publishersVersionField
   *   The field to save url to.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function savePublishersVersionUrl($url, FieldItemListInterface $publishersVersionField) {
    $publishersVersionField->setValue([
      'title' => $this->t("Publisher's Version"),
      'uri' => $url,
      'options' => [],
    ]);
  }

}
