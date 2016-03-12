<?php
/**
 * @file
 * Contains Drupal\akamai\Controller\StatusLogController.
 */

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
  public function __construct(StatusStorage $status_storage, DateFormatter $dateFormatter) {
    $this->statusStorage = $status_storage;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generates a table of all request statuses.
   *
   * @return array
   *   A table render array of all requests statuses.
   */
  public function listAction() {

    $statuses = $this->statusStorage->getResponseStatuses();
    $rows = [];
    if (count($statuses)) {
      foreach ($statuses as $status) {
        // Get the most recent request sent regarding this purge.
        $status = array_pop($status);
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

    return $build;
  }

  /**
   * Creates a table row from a status.
   *
   * @param array $status
   *   A status as an array.
   *
   * @return array
   *   An array suitable for embedding as table row.
   */
  protected function statusAsTableRow($status) {
    $url = Url::fromRoute('akamai.statuslog_purge_check', ['purge_id' => $status['purgeId']]);
    $row[] = $this->dateFormatter->format($status['request_made_at'], 'html_datetime');
    $row[] = implode($status['urls_queued'], ', ');
    $row[] = $this->l($status['purgeId'], $url);
    $row[] = $status['supportId'];
    $row[] = $status['httpStatus'];
    $row[] = $status['detail'];
    $row[] = $status['pingAfterSeconds'];
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
      //$this->t('HTTP Code'),
      $this->t('Purge Status'),
      //$this->t('Ping after seconds'),
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
    // @todo inject
    $client = \Drupal::service('akamai.edgegridclient');
    $build[] = $this->purgeStatusTable(Json::decode($client->getPurgeStatus($purge_id)->getBody()));
    return $build;
  }

  /**
   * Builds a table render array for an individual purge request.
   *
   * @param array $status
   *   The purge status.
   *
   * @return array
   *   Table render array with details of request.
   */
  protected function purgeStatusTable($status) {
    $rows = [];
    foreach ($status as $key => $value) {
      $row = [$key, $value];
      $rows[] = $row;
    }

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
