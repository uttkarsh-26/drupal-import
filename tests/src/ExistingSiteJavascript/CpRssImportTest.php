<?php

namespace Drupal\Tests\cp_import\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * CpRssImportTest.
 *
 * @group functional-javascript
 * @group cp
 */
class CpRssImportTest extends OsExistingSiteJavascriptTestBase {
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
    $group_admin = $this->createUser();
    $this->addGroupAdmin($group_admin, $this->group);
    $this->drupalLogin($group_admin);
  }

  /**
   * Checking the form RSS field visibility.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   */
  public function testRssForm() {
    $web_assert = $this->assertSession();
    $this->visitViaVsite('cp/content/import/news', $this->group);
    $web_assert->statusCodeEquals(200);
    $this->getCurrentPage()->find('css', '#cp-content-import-form #edit-format-rss')->click();
    $this->waitForAjaxToFinish();
    $found = $web_assert->waitForElementVisible('css', '#edit-title');
    $this->assertTrue(!is_null($found), 'RSS Title field not showing');
    $found = $web_assert->waitForElementVisible('css', '#edit-rss-url');
    $this->assertTrue(!is_null($found), 'RSS URL field not showing');
    $web_assert->elementsCount('css', '.block-local-tasks-block li.tabs__tab', 2);
    $tab_name = $this->getCurrentPage()->find('css', '.block-local-tasks-block .tabs')->getText();
    $this->assertEquals('Primary tabs Feeds Form(active tab) Feeds Preview', $tab_name, 'Tab names are not same!');
    $this->visitViaVsite('cp/content/import/news', $this->group);
    $web_assert->statusCodeEquals(200);
    $web_assert->elementsCount('css', '.block-local-tasks-block li.tabs__tab', 2);
    $this->visitViaVsite('cp/content/import/page', $this->group);
    $web_assert->statusCodeEquals(200);
    $web_assert->elementsCount('css', '.block-local-tasks-block li.tabs__tab', 0);
  }

  /**
   * Testing the RSS import feed.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRssImportFeed() {
    $web_assert = $this->assertSession();
    $feed = $this->entityTypeManager->getStorage('feeds_feed')->create([
      'type' => 'news_rss_feed',
      'uuid' => $this->uuidGenerator()->generate(),
      'title' => 'Test RSS feed',
      'uid' => 1,
      'status' => 1,
      'created' => \Drupal::time()->getCurrentTime(),
      'source' => 'https://rss.nytimes.com/services/xml/rss/nyt/Television.xml',
      'field_vsite' => '',
      'field_app_name' => '',
    ]);
    $feed->save();
    $this->assertNotEmpty($feed->id());
    $this->assertEquals('Test RSS feed', $feed->label());
    $feed_data = $feed->toArray();
    $this->assertEquals('news_rss_feed', $feed_data['type'][0]['target_id']);
    $this->assertEquals('https://rss.nytimes.com/services/xml/rss/nyt/Television.xml', $feed_data['source'][0]['value']);
    $this->visitViaVsite('cp/content/browse/news/feed', $this->group);
    $web_assert->statusCodeEquals(200);
    $this->visitViaVsite('cp/feed/' . $feed->id() . '/preview/news', $this->group);
    $web_assert->statusCodeEquals(200);
    // Search fieldset functionality.
    $this->assertTrue($this->getSession()->getPage()->find('css', 'input[type=search][name="title"]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->find('css', '#edit-status.form-select')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findButton('Filter')->isVisible());
    $this->getSession()->getPage()->findButton('Filter')->click();
    $this->assertTrue(str_contains($this->getSession()->getCurrentUrl(), 'cp/feed/' . $feed->id() . '/preview/news?title=&status='));
    $this->assertTrue($this->getSession()->getPage()->find('css', '.pager')->isVisible());
    // Importing a content.
    $title_preview = $this->getSession()->getPage()->find('css', '#cp-content-import-rss-form table tbody tr:first-child td:nth-child(2)')->getText();
    $this->getSession()->getPage()->find('css', 'input[type=checkbox][name="table[0]"]')->check();
    $this->assertTrue($this->getSession()->getPage()->find('css', 'input[type=checkbox][name="table[0]"]')->isChecked());
    $this->assertTrue($this->getSession()->getPage()->findButton('Import')->isVisible());
    $this->getSession()->getPage()->findButton('Import')->click();
    $web_assert->statusCodeEquals(200);
    $this->visitViaVsite('cp/content/browse/node', $this->group);
    $title = $this->getCurrentPage()->find('css', '#views-form-site-content-page-1 tbody .views-field-title a')->getText();
    $this->assertEquals($title, $title_preview);
    // Disable the feeds.
    $feed1 = $this->entityTypeManager->getStorage('feeds_feed')->load($feed->id());
    $feed1->set('status', 0);
    $feed1->save();
    $this->visitViaVsite('cp/content/browse/news/feed', $this->group);
    $web_assert->statusCodeEquals(200);
    // Delete the feed.
    $status = $feed->delete();
    $this->assertNull($status);
  }

}
