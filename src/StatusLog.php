<?php
/**
 * @file
 * Contains Drupal\akamai\StatusLog.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Drupal\Component\Serialization\Json;

/**
 * A logging utility for keeping track of Akamai purge statuses.
 */
class StatusLog {

  /**
   * State key for keeping track of purge statuses.
   */
  const PURGE_STATUS_KEY = 'akamai.purge_status';

  /**
   * Config from akamai.settings.
   */
  protected $config;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Build the status logger.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory, to get Akamai configuration.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->config = $config_factory->get('akamai.settings');
    $this->logger = $logger;
  }

  /**
   * Keeps track of response statuses so we can reference them later.
   *
   * @param Response $response
   *   Response object, returned from a successful CCU call.
   * @param array $queued_urls
   *   A list of URLs enqueued in this request.
   */
  public function saveResponseStatus(Response $response, $queued_urls) {
    $statuses = $this->getResponseStatuses();
    $response_body = Json::decode($response->getBody());
    // Add a request made timestamp so we can compare later.
    $response_body['request_made_at'] = REQUEST_TIME;
    $response_body['urls_queued'] = $queued_urls;
    $statuses[] = $response_body;
    \Drupal::state()->set(StatusLog::PURGE_STATUS_KEY, $statuses);
  }

  /**
   * Return a list of response statuses.
   *
   * @return array
   *   An array of responses.
   */
  public static function getResponseStatuses() {
    return \Drupal::state()->get(StatusLog::PURGE_STATUS_KEY);
  }

}
