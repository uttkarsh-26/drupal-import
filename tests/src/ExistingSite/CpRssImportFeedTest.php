<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpRssImportFeedTest.
 *
 * @group kernel
 * @group cp-1
 */
class CpRssImportFeedTest extends OsExistingSiteTestBase {
  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function uuidGenerator() {
    return \Drupal::service('uuid');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->groupMember = $this->createUser();
    $this->group->addMember($this->groupMember);
    $this->drupalLogin($this->groupMember);
  }

  /**
   * Testing the RSS import feed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRssImportFeed() {
    $feed = $this->entityTypeManager->getStorage('feeds_feed')->create([
      'type' => 'news_rss_feed',
      'uuid' => $this->uuidGenerator()->generate(),
      'title' => 'RSS feed',
      'uid' => 1,
      'status' => 1,
      'created' => \Drupal::time()->getCurrentTime(),
      'source' => 'http://rss.cnn.com/rss/cnn_tech.rss',
      'field_vsite' => '',
      'field_app_name' => '',
    ]);
    $feed->save();
    $this->assertNotEmpty($feed->id());
    $this->assertEquals('RSS feed', $feed->label());
    $feed_data = $feed->toArray();
    $this->assertEquals('news_rss_feed', $feed_data['type'][0]['target_id']);
    $this->assertEquals('http://rss.cnn.com/rss/cnn_tech.rss', $feed_data['source'][0]['value']);
  }

}
