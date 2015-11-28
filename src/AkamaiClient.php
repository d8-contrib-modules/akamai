<?php
/**
 * @file
 * Contains \Drupal\akamai\AkamaiClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\Config;
use Akamai\Open\EdgeGrid\Client;

/**
 * Connects to the Akamai EdgeGrid.
 */
class AkamaiClient extends Client {

  /**
   * The settings configuration.
   *
   * @note GuzzleHttp\Client has its own private property called $config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $drupalConfig;

  /**
   * A config suitable for use with Akamai\Open\EdgeGrid\Client.
   *
   * @var array
   */
  protected $akamaiClientConfig;

  /**
   * Base url to which API method names are appended.
   *
   * @var string
   */
  protected $apiBaseUrl = '/ccu/v2/';

  /**
   * A  list of objects to clear.
   *
   * @var array
   */
  protected $purgeList;

  /**
   * Factory method to create instances of the Client based on Drupal config.
   *
   * @todo I think we can move this back to the constructor
   *
   * @param \Drupal\Core\Config\Config $config
   *   A Drupal config object containing Akamai settings and credentials.
   *
   * @return \Drupal\akamai\AkamaiClient
   *   A web services client, ready for use.
   */
  public static function create(Config $config) {
    $akamai_client_config = array();

    // If we are in devel mode, use the mocked endpoint.
    $akamai_client_config['base_uri'] = $config->get('akamai_devel_mode')
      ? $config->get('akamai_mock_endpoint')
      : $config->get('akamai_restapi_endpoint');

    $akamai_client_config['timeout'] = $config->get('akamai_timeout');

    // $auth = AkamaiAuthentication::create($config);
    // @see Client::createFromEdgeRcFile()
    $client = new static($akamai_client_config);
    // Set the auth credentials up.
    // @see Authentication::createFromEdgeRcFile()
    $client->setAuth(
      $config->get('client_token'),
      $config->get('client_secret'),
      $config->get('access_token')
    );

    return $client;
  }

  /**
   * Creates a config array for consumption by Akamai\Open\EdgeGrid\Client.
   *
   * @return array
   *   The config array.
   *
   * @see Akamai\Open\EdgeGrid\Client::setBasicOptions
   */
  protected function createClientConfig() {
    // @note this functionality has been moved to create().
    // @todo do we need to keep the config in akamai format arbitrarily?
    // If we are in devel mode, use the mocked endpoint.
    if ($this->drupalConfig->get('akamai_devel_mode') == TRUE) {
      $this->akamaiClientConfig['base_uri'] = $this->drupalConfig->get('akamai_mock_endpoint');
    }
    // @todo Add real API endpoint config

    $this->akamaiClientConfig['timeout'] = $this->drupalConfig->get('akamai_timeout');

    return $this->akamaiClientConfig;
  }


  /**
   * Purges a single URL object.
   *
   * @param string $url
   *   A URL to clear.
   */
  public function purgeUrl($url) {
    $this->purgeUrls(array($url));
  }

  /**
   * Purges a list of URL objects.
   *
   * @param array $urls
   *   List of URLs to purge.
   */
  public function purgeUrls($urls) {
    $this->purgeRequest($urls);
  }

  /**
   * Ask the API to purge an object.
   *
   * @param array $objects
   *   A non-associative array of Akamai objects to clear.
   * @param string $queue
   *   The queue name to clear.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *    Response to purge request.
   *
   * @link https://developer.akamai.com/api/purge/ccu/reference.html
   * @link https://github.com/akamai-open/api-kickstart/blob/master/examples/php/ccu.php#L58
   */
  protected function purgeRequest($objects, $queue = 'default') {
    // Note that other parameters are defaulted:
    // action: remove (default), invalidated
    // domain: production (default), staging
    // type: arl (default), cpcode.
    $response = $this->request(
      'POST',
      $this->apiBaseUrl . 'queues/' . $queue,
      ['json' => $this->createPurgeBody($objects)]
    );

    // Note that the response has useful data that we need to record.
    // @todo Keep track of purgeId, estimatedSeconds, checkAfterSeconds.
    return $response;
  }

  /**
   * Add a URL to the internal list of URLs to purge.
   *
   * @param string $url
   *   A URL to clear.
   */
  protected function addToPurgeList($url) {
    $this->purgeList[] = $url;
  }

  /**
   * Create an array to pass to Akamai's purge function.
   *
   * @param array $urls
   *   A list of URLs.
   *
   * @return array
   *   An array suitable for sending to the Akamai purge endpoint.
   */
  protected function createPurgeBody($urls) {
    return [
      'objects' => $urls,
    ];
  }

  // @todo Create diagnostic check classes to consume these.

  /**
   * Get a queue to check its status.
   *
   * @param string $queue_name
   *   The queue name to check. Defaults to 'default'.
   *
   * @return object
   *    Response body of request.
   *
   * @link https://api.ccu.akamai.com/ccu/v2/docs/#section_CheckingQueueLength
   * @link https://developer.akamai.com/api/purge/ccu/reference.html
   */
  protected function getQueue($queue_name = 'default') {
    $response = $this->get("/ccu/v2/queues/{$queue_name}");
    return json_decode($response->getBody());
  }

  /**
   * Get the number of items remaining in the purge queue.
   *
   * @return int
   *   A count of the remaining items in the purge queue.
   */
  public function getQueueLength() {
    return $this->getQueue->queueLength;
  }

  /**
   * Return the status of a previous purge request.
   *
   * @param string $purge_id
   *   The UUID of the purge request to check.
   */
  protected function getPurgeStatus($purge_id) {
    // @todo Implement purge checking once we are tracking purge ids.
  }

}
