<?php

namespace Drupal\akamai\Controller;

use Drupal\akamai\PurgeStatus;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\akamai\StatusStorage;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

/**
 * Provides route callback utilities to browse and administer Akamai purges.
 */
class StatusLogController extends ControllerBase {

  /**
   * Status logging service.
   *
   * @var \Drupal\akamai\StatusStorage
   */
  protected $statusStorage;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('akamai.status_storage'),
      $container->get('date.formatter')
    );
  }

  /**
   * StatusLogController constructor.
   *
   * @param \Drupal\akamai\StatusStorage $status_storage
   *   A status storage service, so we can reference statuses.
   */
  public function __construct(StatusStorage $status_storage, DateFormatter $date_formatter) {
    $this->statusStorage = $status_storage;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Generates a table of all request statuses.
   *
   * @return array
   *   A table render array of all requests statuses.
   */
  public function listAction() {
    $client = \Drupal::service('akamai.edgegridclient');
    $length = $client->getQueueLength();

    $statuses = $this->statusStorage->getResponseStatuses();
    $rows = [];
    if (count($statuses)) {
      foreach ($statuses as $status) {
        // Get the most recent request sent regarding this purge.
        $status = new PurgeStatus($status);
        $rows[] = $this->statusAsTableRow($status);
      }
    }
    else {
      $rows[] = [
        [
          'data' => $this->t('No purges found.'),
          'colspan' => 5,
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#header' => $this->statusTableHeader(),
      '#sticky' => TRUE,
    ];

    $build[] = [
      '#markup' => $this->t('Items remaining in Akamai purge queue: %length', ['%length' => $length]),
    ];

    return $build;
  }

  /**
   * Creates a table row from a status.
   *
   * @param PurgeStatus $status
   *   A status as an array.
   *
   * @return array
   *   An array suitable for embedding as table row.
   */
  protected function statusAsTableRow(PurgeStatus $status) {
    $url = Url::fromRoute('akamai.statuslog_purge_check', ['purge_id' => $status->getPurgeId()]);

    $row[] = $this->dateFormatter->format($status->getLastCheckedTime(), 'html_datetime');
    $row[] = implode($status->getUrls(), ', ');
    $row[] = $this->l($status->getPurgeId(), $url);
    $row[] = $status->getSupportId();
    $row[] = $status->getDescription();

    return $row;
  }

  /**
   * Creates a table header array for a status list table.
   *
   * @return array
   *   Array of header values.
   */
  protected function statusTableHeader() {
    return [
      // @todo set responsive priority. @see theme.inc
      $this->t('Request made'),
      $this->t('URLs'),
      $this->t('Purge ID'),
      $this->t('Support ID'),
      $this->t('Purge Status'),
    ];
  }

  /**
   * Callback for a page showing the status of a purge.
   *
   * @param string $purge_id
   *   Purge ID to check.
   *
   * @return array
   *   A render array with purge details.
   */
  public function checkPurgeAction($purge_id) {
    // @todo convert to breadcrumb
    $build[]['#markup'] = '<p>' . $this->l($this->t('Back to list'), Url::fromRoute('akamai.statuslog_list')) . '</p>';

    // @todo inject
    $client = \Drupal::service('akamai.edgegridclient');
    // Get a new status update.
    $status = Json::decode($client->getPurgeStatus($purge_id)->getBody());
    // Save it in storage.
    $this->statusStorage->save($status);
    // Now get it back so we can use object functions.
    $status = new PurgeStatus($this->statusStorage->get($purge_id));

    $build[] = $this->purgeStatusTable($status);

    $links['delete'] = [
      'title' => $this->t('Delete this log entry'),
      'url' => Url::fromRoute('akamai.statuslog_delete', ['purge_id' => $purge_id]),
    ];
    $build[] = [
      '#type' => 'operations',
      '#links' => $links,
    ];

    return $build;
  }

  /**
   * Builds a table render array for an individual purge request.
   *
   * @param PurgeStatus $status
   *   The purge status.
   *
   * @return array
   *   Table render array with details of request.
   */
  protected function purgeStatusTable(PurgeStatus $status) {
    $item_list = implode(', ', $status->getUrls());
    $rows = [
      [$this->t('Purge ID'), $status->getPurgeId()],
      [$this->t('Support ID'), $status->getSupportId()],
      [$this->t('Description'), $status->getDescription()],
      [$this->t('URLs'), $item_list],
      [
        $this->t('Last checked'),
        $this->dateFormatter->format($status->getLastCheckedTime(), 'html_datetime'),
      ],
      [$this->t('HTTP Status'), $status->getHttpCode()],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#header' => [$this->t('Key'), $this->t('Value')],
    ];

    return $build;
  }

  /**
   * Returns a page title when directly checking a purge (without Ajax).
   *
   * @param string $purge_id
   *   The Purge ID to check, passed in from the route.
   *
   * @return string
   *   A title suitable for including in an HTML tag.
   */
  public function checkPurgeTitle($purge_id) {
    return $this->t('Purge status for purge id :purge_id', [':purge_id' => $purge_id]);
  }

}
