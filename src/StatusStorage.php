<?php
/**
 * @file
 * Contains Drupal\akamai\StatusStorage.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Drupal\Component\Serialization\Json;

/**
 * A logging utility for keeping track of Akamai purge statuses.
 *
 * @todo Make a PurgeStatus class instead of manipulating arrays.
 */
class StatusStorage {

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
    // @todo note that several individual web service calls may be consolidated
    // into a single request with a single purge id.
    // We need to add to the existing
    $response_body = Json::decode($response->getBody());
    // Add a request made timestamp so we can compare later.
    $response_body['urls_queued'] = $queued_urls;
    $this->save($response_body);
  }

  /**
   * Saves an individual status response.
   *
   * @param array $status
   *   A raw status array returned from a client request.
   */
  public function save($status) {
    $statuses = $this->getResponseStatuses();
    $status['request_made_at'] = REQUEST_TIME;
    $statuses[$status['purgeId']][] = $status;
    // Key the response log by the purge UUID.
    // Note that one purge may contain several requests.
    $this->saveStatuses($statuses);
  }

  /**
   * Saves an array of statuses to state.
   *
   * @param array $statuses
   *   An array of status arrays.
   */
  protected function saveStatuses($statuses) {
    \Drupal::state()->set(StatusStorage::PURGE_STATUS_KEY, $statuses);
  }

  /**
   * Return a list of response statuses.
   *
   * @return array
   *   An array of status arrays.
   */
  public static function getResponseStatuses() {
    return \Drupal::state()->get(StatusStorage::PURGE_STATUS_KEY);
  }

  /**
   * Finds an individual request status with a matching purge ID.
   *
   * @param string $purge_id
   *   Purge ID to search for.
   *
   * @return array|FALSE
   *   The status array if found, FALSE if not.
   */
  protected function getStatusByPurgeId($purge_id) {
    $statuses = $this->getResponseStatuses();
    if (array_key_exists($purge_id, $statuses)) {
      return $statuses[$purge_id];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function get($purge_id) {
    return $this->getStatusByPurgeId($purge_id);
  }

  /**
   * Deletes a purge ID from the status log.
   *
   * @param string $purge_id
   *   Purge ID to delete.
   */
  protected function deleteStatusByPurgeId($purge_id) {
    $statuses = $this->getResponseStatuses();
    unset($statuses[$purge_id]);
    $this->saveStatuses($statuses);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    $this->deleteStatusByPurgeId($id);
  }

}
