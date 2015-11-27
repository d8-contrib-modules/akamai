<?php

namespace Drupal\akamai;

use Drupal\Core\Config\Config;
use Akamai\Open\EdgeGrid\Client;
use Drupal\akamai\AkamaiAuthentication;

/**
 * Connects to the Akamai EdgeGrid.
 */
class AkamaiClient extends Client {

  /**
   * The settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

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
   * Factory method to create instances of the Client based on Drupal config.
   *
   * @param \Drupal\Core\Config\Config $config
   *   A Drupal config object containing Akamai settings and credentials.
   *
   * @return \Drupal\akamai\AkamaiClient
   *   A web services client, ready for use.
   */
  public static function create(Config $config) {
    // If we are in devel mode, use the mocked endpoint.
    if ($config->get('akamai_devel_mode') == TRUE) {
      $akamai_client_config['base_uri'] = $config->get('akamai_mock_endpoint');
    }
    // @todo Add real API endpoint config
    $akamai_client_config['timeout'] = $config->get('akamai_timeout');

    $auth = AkamaiAuthentication::create($config);
    // @see Client::createFromEdgeRcFile()
    $client = new static($akamai_client_config, $auth);

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
    if ($this->config->get('akamai_devel_mode') == TRUE) {
      $this->akamaiClientConfig['base_uri'] = $this->config->get('akamai_mock_endpoint');
    }
    // @todo Add real API endpoint config

    $this->akamaiClientConfig['timeout'] = $this->config->get('akamai_timeout');

    return $this->akamaiClientConfig;
  }


  /**
   * Purges a single URL object.
   *
   * @param string $url
   *   A URL to clear.
   */
  protected function purgeUrl($url) {
    $this->purgeRequest(array($url));
  }


  /**
   * Ask the API to purge an object.
   *
   * @param array $objects
   *   A non-associative array of Akamai objects to clear.
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
    // type: arl (default), cpcode
    $response = $this->post(
      $this->apiBaseUrl . '/queues/' . $queue,
      [
        'body' => json_encode($objects),
        'headers' => ['Content Type' => 'application/json'],
      ]
    );

    return $response;
  }




  // @todo Create diagnostic check classes to consume these.

  /**
   * Get a queue to check its status.
   *
   * @param string $queue_name
   *   The queue name to check. Defaults to 'default'.
   *
   * @return StdClass
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

}
