<?php

namespace Drupal\cp_import\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Tableselect;
use Drupal\Core\Url;
use Drupal\feeds_rss_preview\Controller\Preview;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CpRssImportForm.
 */
class CpRssImportForm extends FormBase {

  /**
   * Entity Type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Vsite context manager.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */
  protected $vsiteContextManager;

  /**
   * PagerManager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TimeInterface $time = NULL, VsiteContextManagerInterface $vsite_context_manager, DateFormatter $dateFormatter, PagerManagerInterface $pager_manager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->vsiteContextManager = $vsite_context_manager;
    $this->dateFormatter = $dateFormatter;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('vsite.context_manager'),
      $container->get('date.formatter'),
      $container->get('pager.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cp_content_import_rss_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $feed_id = NULL, $app_name = NULL) {
    $session = $this->getRequest()->getSession();
    $feed = $this->entityTypeManager->getStorage('feeds_feed')->load($feed_id);
    $session_name = 'cp_rss_import_data_' . $feed_id;
    if (empty($session->get($session_name))) {
      $preview = new Preview();
      $results = $preview->previewFeed($feed);
      $node_entity = $this->entityTypeManager->getStorage('node');
      $group = $this->vsiteContextManager->getActiveVsite();
      foreach ($results['rows'] as $key => $row) {
        if (empty($results['rows'][$key]['title']['#plain_text']) || $results['rows'][$key]['title']['#plain_text'] === NULL) {
          $results['rows'][$key]['status'] = $this->t('Not Imported');
          $results['rows'][$key]['#disabled'] = TRUE;
        }
        else {
          $this->groupHasContent($results, $node_entity, $group, $key, $app_name);
        }
      }
      $session->set($session_name, $results);
    }
    else {
      $results = $session->get($session_name);
    }
    $title_params = $this->getRequest()->query->get('title');
    $status_params = $this->getRequest()->query->get('status');
    $pager_params = !empty($this->getRequest()->query->get('page')) ? $this->getRequest()->query->get('page') : 0;
    $pager_limit = 10;
    $filter_array = [
      'Imported' => 'imported',
      'Not Imported' => 'import',
    ];
    foreach ($results['rows'] as $key => $row) {
      if (!empty($results['rows'][$key]['timestamp']['#plain_text'])) {
        $results['rows'][$key]['timestamp']['#plain_text'] = $this->dateFormatter
          ->format($results['rows'][$key]['timestamp']['#plain_text'], 'custom', 'Y-m-d H:i:s', date_default_timezone_get());
      }
      if (!empty($title_params) && !preg_match("/{$title_params}/i", $row['title']['#plain_text'])) {
        unset($results['rows'][$key]);
      }
      if (!empty($status_params) && $status_params != $filter_array[$row['status']->__toString()]) {
        unset($results['rows'][$key]);
      }
    }
    $url = Url::fromUserInput('/cp/content/browse/' . $app_name . '/feed')->toString();
    $link = '<a href="' . $url . '">' . $this->t('Back to Feeds') . '</a>';
    $this->pagerManager->createPager(count($results['rows']), $pager_limit);
    $data = array_chunk($results['rows'], $pager_limit);
    $form['filters'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Filter'),
      '#open'  => TRUE,
    ];
    $form['filters']['title'] = [
      '#title' => 'Title',
      '#type' => 'search',
      '#value' => !empty($title_params) ? $title_params : '',
    ];
    $form['filters']['status'] = [
      '#title' => 'Status',
      '#type' => 'select',
      '#options' => [
        'import' => $this->t('Not Imported'),
        'imported' => $this->t('Imported'),
      ],
      '#value' => !empty($status_params) ? strtolower($status_params) : '',
      '#empty_option' => $this->t('- Select One -'),
    ];
    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];
    $form['filters']['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => ['::filterSubmit'],
    ];
    $results['header']['status'] = 'status';
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $results['header'],
      '#options' => !empty($data[$pager_params]) ? $data[$pager_params] : [],
      '#process' => [
        // This is the original #process callback.
        [Tableselect::class, 'processTableselect'],
        // Additional #process callback.
        [static::class, 'processDisabledRows'],
      ],
      '#prefix' => $link,
      '#empty' => $this->t('There is no data available.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
    $form['rows'] = [
      '#type' => 'value',
      '#value' => $data[$pager_params],
    ];
    $form['feed_info'] = [
      '#type' => 'value',
      '#value' => [
        'feed' => $feed,
        'app_name' => $app_name,
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#suffix' => $link,
    ];

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = $form_state->getValue('table');
    $rows = $form_state->getValue('rows');
    $feed_info = $form_state->getValue('feed_info');
    $index = 0;
    $group = $this->vsiteContextManager->getActiveVsite();
    if (!isset($group)) {
      // Activate the Vsite group if the RSS import redirects from admin URL.
      $vsite_id = $feed_info['feed']->get('field_vsite')->value;
      $entity_group = $this->entityTypeManager->getStorage('group')->load($vsite_id);
      $this->vsiteContextManager->activateVsite($entity_group);
      $group = $this->vsiteContextManager->getActiveVsite();
    }
    foreach ($selected as $select) {
      if (is_string($select)) {
        if (!empty($rows[$select]['timestamp']['#plain_text'])) {
          if (strpos($rows[$select]['timestamp']['#plain_text'], ' ') !== FALSE) {
            $time = explode(' ', $rows[$select]['timestamp']['#plain_text']);
            $date = !empty($time[0]) ? $time[0] : NULL;
          }
          else {
            $date = date('Y-m-d', $rows[$select]['timestamp']['#plain_text']);
          }
        }
        if ($feed_info['app_name'] === 'news') {
          $data = [
            'type' => $feed_info['app_name'],
            'title' => $rows[$select]['title']['#plain_text'],
            'field_date' => ($date === NULL) ? date('Y-m-d') : $date,
            'body' => [
              'value' => $rows[$select]['content']['#plain_text'],
              'format' => 'filtered_html',
            ],
            'field_redirect_to_source' => $rows[$select]['url']['#plain_text'],
          ];
        }
        elseif ($feed_info['app_name'] === 'blog') {
          $data = [
            'type' => $feed_info['app_name'],
            'title' => $rows[$select]['title']['#plain_text'],
            'body' => [
              'value' => $rows[$select]['content']['#plain_text'],
              'format' => 'filtered_html',
            ],
          ];
        }
        $node = $this->entityTypeManager->getStorage('node')->create($data);
        $node->save();
        $group->addContent($node, 'group_node:' . $feed_info['app_name']);
        $index++;
      }
    }
    $url = Url::fromUserInput('/cp/content/import/' . $feed_info['app_name']);
    $this->messenger()->addMessage($this->t('%cnt %type of contents are created', [
      '%cnt' => $index,
      '%type' => $feed_info['app_name'],
    ]));
    $session = $this->getRequest()->getSession();
    $session->remove('cp_rss_import_data_' . $feed_info['feed']->id());
    $form_state->setRedirectUrl($url);
  }

  /**
   * Add query string.
   *
   * @param array $form
   *   The Form value.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormStateInterface.
   */
  public function filterSubmit(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $args = [
      'title' => $input['title'],
      'status' => $input['status'],
    ];
    $url = $this->url = Url::fromRoute('<current>');
    $url->setOptions(['query' => $args]);

    $form_state->setRedirectUrl($url);
  }

  /**
   * Disable the input field if already imported.
   *
   * @param array $element
   *   The Element.
   *
   * @return array
   *   The Element.
   */
  public static function processDisabledRows(array &$element): array {
    foreach (Element::children($element) as $key) {
      $element[$key]['#disabled'] = isset($element['#options'][$key]['#disabled']) ? $element['#options'][$key]['#disabled'] : FALSE;
    }
    return $element;
  }

  /**
   * Check the content is present in the group.
   *
   * @param array $results
   *   RSS Rows.
   * @param object $node_entity
   *   Node object.
   * @param object $group
   *   Group Object.
   * @param string $key
   *   Iteration.
   * @param string $app_name
   *   Vsite APP name.
   */
  public function groupHasContent(array &$results, $node_entity, $group, $key, $app_name) {
    $status = FALSE;
    $node = $node_entity->loadByProperties(['title' => $results['rows'][$key]['title']['#plain_text']]);
    if (!empty($node)) {
      foreach ($node as $node_obj) {
        $group_node = $group->getContentByEntityId('group_node:' . $app_name, $node_obj->id());
        if (!empty($group_node)) {
          $status = TRUE;
        }
      }
    }
    $results['rows'][$key]['status'] = $status ? $this->t('Imported') : $this->t('Not Imported');
    $results['rows'][$key]['#disabled'] = $status ? TRUE : FALSE;
  }

}
