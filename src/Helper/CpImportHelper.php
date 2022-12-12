<?php

namespace Drupal\cp_import\Helper;

use DateTime;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Session\AccountProxy;
use Drupal\media\Entity\Media;
use Drupal\os_media\MediaEntityHelper;
use Drupal\pathauto\PathautoGenerator;
use Drupal\vsite\Plugin\VsiteContextManager;
use League\Csv\Reader;
use League\Csv\Writer;
use Drupal\Core\File\Exception\DirectoryNotReadyException;
use Drupal\Core\Database\Connection;
use Drupal\group\Entity\GroupInterface;

/**
 * Class CpImportHelper.
 *
 * @package Drupal\cp_import\Helper
 */
class CpImportHelper extends CpImportHelperBase {

  /**
   * Csv row limit.
   */
  const CSV_ROW_LIMIT = 100;

  /**
   * PubMed ID list limit.
   */
  const PUBMED_ID_LIST_LIMIT = 10;

  /**
   * Csv row limit string.
   */
  const OVER_LIMIT = 'rows_over_allowed_limit';

  /**
   * Media Helper service.
   *
   * @var \Drupal\os_media\MediaEntityHelper
   */
  protected $mediaHelper;

  /**
   * Current User service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * FileSystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * PathAutoGenerator service.
   *
   * @var \Drupal\pathauto\PathautoGenerator
   */
  protected $pathAutoGenerator;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConnection;

  /**
   * CpImportHelper constructor.
   *
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsiteContextManager
   *   VsiteContextManager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager instance.
   * @param \Drupal\os_media\MediaEntityHelper $mediaHelper
   *   MediaEntityHelper instance.
   * @param \Drupal\Core\Session\AccountProxy $user
   *   AccountProxy instance.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   LanguageManager instance.
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   EntityFieldManager instance.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   FileSystem interface.
   * @param \Drupal\pathauto\PathautoGenerator $pathautoGenerator
   *   PathAutoGenerator instance.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(VsiteContextManager $vsiteContextManager, EntityTypeManagerInterface $entity_type_manager, MediaEntityHelper $mediaHelper, AccountProxy $user, LanguageManager $languageManager, EntityFieldManager $entityFieldManager, FileSystemInterface $fileSystem, PathautoGenerator $pathautoGenerator, Connection $connection) {
    parent::__construct($vsiteContextManager, $entity_type_manager);
    $this->mediaHelper = $mediaHelper;
    $this->currentUser = $user;
    $this->languageManager = $languageManager;
    $this->fieldManager = $entityFieldManager;
    $this->fileSystem = $fileSystem;
    $this->pathAutoGenerator = $pathautoGenerator;
    $this->dbConnection = $connection;
  }

  /**
   * Get the media to be attached to the node.
   *
   * @param string $media_val
   *   The media value entered in the csv.
   * @param string $contentType
   *   Content type.
   * @param string $field_name
   *   Media field name.
   * @param string $entity_type
   *   Entity type.
   * @param \Drupal\group\Entity\GroupInterface|null $vsite
   *   Vsite group.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity/Null when not able to fetch/download media.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getMedia($media_val, $contentType, $field_name, $entity_type = 'node', GroupInterface $vsite = NULL) : ?Media {
    $media = NULL;
    $media_val = str_replace(" ", "%20", $media_val);
    $media_val = str_replace("\t", "%20", $media_val);
    // Only load the bundles which are enabled for the content type's field.
    $bundle_fields = $this->fieldManager->getFieldDefinitions($entity_type, $contentType);
    $field_definition = $bundle_fields[$field_name];
    $settings = $field_definition->getSettings();
    if (!empty($settings['handler_settings'])) {
      $bundles = $settings['handler_settings']['target_bundles'];
    }
    elseif ($settings['target_type'] === 'file') {
      $bundles = [
        'image' => 'image',
      ];
    }
    /** @var \Drupal\media\Entity\MediaType[] $mediaTypes */
    $mediaTypes = $this->entityTypeManager->getStorage('media_type')->loadMultiple($bundles);
    $item = get_headers($media_val, 1);
    $type = $item['Content-Type'];
    // If there is a redirection then only get the value of the last page.
    $type = is_array($type) ? end($type) : $type;
    if (strpos($type, 'text/html') !== FALSE) {
      $media = $this->createOembedMedia($media_val, $mediaTypes);
    }
    else {
      $media = $this->createMediaWithFile($media_val, $mediaTypes, $vsite);
    }
    return $media;
  }

  /**
   * Helper method to convert csv to array.
   *
   * @param string $filename
   *   File uri.
   * @param string $encoding
   *   Encoding of the file.
   *
   * @return array|string
   *   Data as an array or error string.
   *
   * @throws \League\Csv\CannotInsertRecord
   * @throws \League\Csv\Exception
   */
  public function csvToArray($filename, $encoding) {

    if (!file_exists($filename) || !is_readable($filename)) {
      return FALSE;
    }
    $header = NULL;
    $data = [];

    $csv = Reader::createFromPath($filename, 'r');
    // Let's set the output BOM.
    $csv->setOutputBOM(Reader::BOM_UTF8);
    // Let's convert the incoming data to utf-8.
    $csv->addStreamFilter("convert.iconv.$encoding/utf-8");

    foreach ($csv as $row) {
      if (!$header) {
        $header = $row;
      }
      else {
        // If header and row column numbers don't match , csv file structure is
        // incorrect and needs to be updated.
        if (count($header) !== count($row)) {
          return [];
        }
        $data[] = array_combine($header, $row);
      }
    }

    // If no data rows then we do not need to proceed but throw error.
    if (!$data) {
      return [];
    }
    if (count($data) > self::CSV_ROW_LIMIT) {
      return self::OVER_LIMIT;
    }

    if (!in_array('Timestamp', $header)) {
      // Put values encoded to utf-8 in the csv source file so that it can be
      // used during migration as it does not support all encodings out of
      // the box.
      $writer = Writer::createFromPath($filename);
      // We use pseudo uniqueid field in the csv to allow same content
      // to be imported on some other vsite which might otherwise will not be
      // imported due to migration unique id requirements.
      array_unshift($header, 'Timestamp');
      $writer->insertOne($header);
      foreach ($data as $row) {
        array_unshift($row, uniqid());
        $writer->insertOne(array_values($row));
      }
    }
    return $data;
  }

  /**
   * Creates Oembed type of media entity.
   *
   * @param string $url
   *   Url obtained from csv.
   * @param array $mediaTypes
   *   Media types for this entity type.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity/Null if not able to fetch embedly resource.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see os_media_media_insert()
   */
  protected function createOembedMedia($url, array $mediaTypes) : ?Media {
    $media_entity = NULL;
    if (!in_array('oembed', array_keys($mediaTypes))) {
      return $media_entity;
    }
    $data = $this->mediaHelper->fetchEmbedlyResource($url);
    if ($data) {
      /** @var \Drupal\media\Entity\Media $media_entity */
      $media_entity = Media::create([
        'bundle' => 'oembed',
        // Name changes later via a presave hook in os_media.
        'name' => 'Placeholder',
        'uid' => $this->currentUser->id(),
        'langcode' => $this->languageManager->getDefaultLanguage()->getId(),
        'field_media_oembed_content' => [
          'value' => $url,
        ],
      ]);
      $media_entity->save();
    }
    return $media_entity;
  }

  /**
   * Creates a Media entity which has a file attached to it.
   *
   * @param string $media_val
   *   Media value from csv.
   * @param array $mediaTypes
   *   Media types for this entity type.
   * @param \Drupal\group\Entity\GroupInterface|null $vsite
   *   Vsite group.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity/Null if not able to download the file.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createMediaWithFile($media_val, array $mediaTypes, GroupInterface $vsite = NULL) : ?Media {
    $media_entity = NULL;
    $file = FALSE;
    /** @var \Drupal\file\FileInterface $file */
    $file = system_retrieve_file($media_val, $this->getUploadLocation($vsite), TRUE);

    // Map and attach file to appropriate Media bundle.
    if ($file) {
      $extension = pathinfo($file->getFileUri(), PATHINFO_EXTENSION);
      if (!$extension) {
        $extension = pathinfo($media_val, PATHINFO_EXTENSION);
      }
      $langCode = $this->languageManager->getDefaultLanguage()->getId();
      foreach ($mediaTypes as $mediaType) {
        $fieldDefinition = $mediaType->getSource()->getSourceFieldDefinition($mediaType);
        if (is_null($fieldDefinition)) {
          continue;
        }
        $exts = explode(' ', $fieldDefinition->getSetting('file_extensions'));
        if (in_array($extension, $exts)) {
          /** @var \Drupal\media\Entity\Media $media_entity */
          $media_entity = Media::create([
            'bundle' => $mediaType->id(),
            'name' => $file->getFilename(),
            'uid' => $this->currentUser->id(),
            'langcode' => $langCode,
            $fieldDefinition->getName() => [
              'target_id' => $file->id(),
            ],
          ]);
          $media_entity->save();
        }
      }
    }
    return $media_entity;
  }

  /**
   * Get the file download/save location.
   *
   * @param \Drupal\group\Entity\GroupInterface|null $vsite
   *   Vsite group.
   *
   * @return string
   *   The path.
   */
  protected function getUploadLocation(GroupInterface $vsite = NULL): string {
    if (!empty($vsite)) {
      /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
      $query = $this->dbConnection->select('path_alias', 'pa')
        ->fields('pa', ['alias'])
        ->condition('pa.path', "/group/{$vsite->id()}")
        ->range(0, 1);
      /** @var \Drupal\Core\Database\StatementInterface|null $result */
      $result = $query->execute();
      if ($result) {
        $item = $result->fetchAssoc();
        if (isset($item['alias'])) {
          $path = 'public://' . trim($item['alias'], '/') . '/files';
        }
      }

      if (!$path) {
        $path = 'public://global';
      }
    }
    elseif ($purl = $this->vsiteManager->getActivePurl()) {
      $path = 'public://' . $purl . '/files';
    }
    else {
      $path = 'public://global';
    }
    if (!$this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new DirectoryNotReadyException("The specified directory could not be created. This may be caused by a problem with file or directory permissions.");
    }
    return $path;
  }

  /**
   * Checks is date is of supported date format.
   *
   * @param string $source_date
   *   Source date string.
   * @param array $supported_formats
   *   Supported date format.
   *
   * @return bool
   *   Returns if date is valid.
   */
  public function validateSourceDate($source_date, array $supported_formats): bool {
    $source_date = strtoupper($source_date);
    foreach ($supported_formats as $format) {
      $date = DateTime::createFromFormat($format, $source_date);
      if ($date && $date->format($format) == $source_date) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns transformed date formated if it is of supported type.
   *
   * @param string $source_date
   *   Source date string.
   * @param array $supported_formats
   *   Supported date format.
   * @param string $transform_format
   *   Return date format.
   *
   * @return string
   *   Returns if date is valid.
   */
  public function transformSourceDate($source_date, array $supported_formats, $transform_format) {
    $source_date = strtoupper($source_date);
    foreach ($supported_formats as $format) {
      $date = DateTime::createFromFormat($format, $source_date);
      if ($date && $date->format($format) == $source_date) {
        return $date->format($transform_format);
      }
    }
    return FALSE;
  }

  /**
   * Returns list of allowed values in semester field.
   *
   * @return array
   *   Returns allowed values in an array format.
   */
  public function getSemesterFieldValues() {
    $fields = $this->fieldManager->getFieldStorageDefinitions('node');
    $options = options_allowed_values($fields['field_semester']);
    return array_keys($options);
  }

}
