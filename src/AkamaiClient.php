<?php
/**
 * @file
 * Contains \Drupal\akamai\AkamaiClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Client;
use Psr\Log\LoggerInterface;

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
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * AkamaiClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->logger = $logger;
    $this->drupalConfig = $config_factory->get('akamai.settings');
    $this->akamaiClientConfig = $this->createClientConfig();

    // $auth = AkamaiAuthentication::create($config);
    // Set the auth credentials up.
    // @see Authentication::createFromEdgeRcFile()
    parent::__construct($this->akamaiClientConfig);
    $this->setAuth(
      $this->drupalConfig->get('client_token'),
      $this->drupalConfig->get('client_secret'),
      $this->drupalConfig->get('access_token')
    );
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
    $client_config = array();
    // If we are in devel mode, use the mocked endpoint.
    if ($this->drupalConfig->get('devel_mode') == TRUE) {
      $client_config['base_uri'] = $this->drupalConfig->get('mock_endpoint');
    }

    // @todo Add real API endpoint config
    $client_config['timeout'] = $this->drupalConfig->get('timeout');

    return $client_config;
  }


  /**
   * Purges a single URL object.
   *
   * @param string $url
   *   A URL to clear.
   *
   * @return GuzzleHttp\Psr7\Response
   *    Response to purge request.
   */
  public function purgeUrl($url) {
    return $this->purgeUrls(array($url));
  }

  /**
   * Purges a list of URL objects.
   *
   * @param array $urls
   *   List of URLs to purge.
   *
   * @return GuzzleHttp\Psr7\Response
   *    Response to purge request.
   */
  public function purgeUrls($urls) {
    return $this->purgeRequest($urls);
  }

  /**
   * Ask the API to purge an object.
   *
   * @param array $objects
   *   A non-associative array of Akamai objects to clear.
   * @param string $queue
   *   The queue name to clear.
   *
   * @return GuzzleHttp\Psr7\Response
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
    // @todo Allow for customisation of request headers above.
    $response = $this->request(
      'POST',
      $this->apiBaseUrl . 'queues/' . $queue,
      ['json' => $this->createPurgeBody($objects)]
    );

    // Note that the response has useful data that we need to record.
    // Example response body:
    // {
    //  "estimatedSeconds": 420,
    //  "progressUri": "/ccu/v2/purges/57799d8b-10e4-11e4-9088-62ece60caaf0",
    //  "purgeId": "57799d8b-10e4-11e4-9088-62ece60caaf0",
    //  "supportId": "17PY1405953363409286-284546144",
    //  "httpStatus": 201,
    //  "detail": "Request accepted.",
    //  "pingAfterSeconds": 420
    //  }
    // @todo Keep track of purgeId, estimatedSeconds, pingAfterSeconds.
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
    return $this->getQueue()->queueLength;
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
