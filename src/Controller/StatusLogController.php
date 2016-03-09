<?php
/**
 * Contains Drupal\akamai\Controller\StatusLogController.
 */

namespace Drupal\akamai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\akamai\StatusLog;

class StatusLogController extends ControllerBase {

  /**
   * Status logging service.
   *
   * @var \Drupal\akamai\StatusLog
   */
  protected $statusLog;

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
      $container->get('akamai.statuslog'),
      $container->get('date.formatter')
    );
  }


  /**
   * StatusLogController constructor.
   *
   * @param \Drupal\akamai\StatusLog $status_log
   *   A status log service, so we can reference statuses.
   */
  public function __construct(StatusLog $status_log, DateFormatter $dateFormatter) {
    $this->statusLog = $status_log;
    $this->dateFormatter = $dateFormatter;
  }

  public function listAction() {
    $statuses = $this->statusLog->getResponseStatuses();
    $rows = [];
    if (count($statuses)) {
      foreach($statuses as $status) {
        $row = [];
        $row[] = $this->dateFormatter->format($status['request_made_at'], 'html_datetime');
        $row[] = implode($status['urls_queued'], ', ');
        //$row[] = $status['progressUri'];
        $row[] = $status['purgeId'];
        $row[] = $status['supportId'];
        $row[] = $status['httpStatus'];
        $row[] = $status['detail'];
        $row[] = $status['pingAfterSeconds'];
        $rows[] = $row;
      }
    }
    else {
      $rows[] = [
        [
          'data' => $this->t('No profiles found'),
          'colspan' => 6,
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#header' => [
        // @todo set responsive priority. @see theme.inc
        $this->t('Request made'),
        // @todo create a JSON callback to check this.
        // The call needs to be authenticated and routed via AkamaiClient
        //$this->t('Check progress'),
        $this->t('URLs'),
        $this->t('Purge ID'),
        $this->t('Support ID'),
        $this->t('HTTP Code'),
        $this->t('Response Message'),
        $this->t('Ping after seconds'),
      ],
      '#sticky' => TRUE,
    ];

    return $build;
  }

}
