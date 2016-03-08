<?php
/**
 * @file
 * Contains \Drupal\akamai\AkamaiClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Client;
use Drupal\Core\Url;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;

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
   * An action to take, either 'remove' or 'invalidate'.
   *
   * @var string
   */
  protected $action = 'remove';

  /**
   * Domain to clear, either 'production' or 'staging'.
   *
   * @var string
   */
  protected $domain = 'production';

  /**
   * Type of purge, either 'arl' or 'cpcode'.
   *
   * @var string
   */
  protected $type = 'arl';

  /**
   * The queue name to clear.
   *
   * @var string
   */
  protected $queue = 'default';

  /**
   * AkamaiClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger = NULL) {
    // @todo remove this
    if (is_null($logger)) {
      $logger = \Drupal::service('logger.channel.akamai');
    }
    $this->logger = $logger;
    $this->drupalConfig = $config_factory->get('akamai.settings');
    $this->akamaiClientConfig = $this->createClientConfig();

    // Set action to take based on configuration.
    $this->setAction(key(array_filter($this->drupalConfig->get('action'))));
    $this->setDomain(key(array_filter($this->drupalConfig->get('domain'))));

    // Create an authentication object so we can sign requests.
    $auth = AkamaiAuthentication::create($this->drupalConfig);
    // Set the auth credentials up.
    // @see Authentication::createFromEdgeRcFile()
    parent::__construct($this->akamaiClientConfig, $auth);
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
    else {
      $client_config['base_uri'] = $this->drupalConfig->get('rest_api_url');
    }

    $client_config['timeout'] = $this->drupalConfig->get('timeout');

    return $client_config;
  }

  /**
   * Checks that we can connect with the supplied credentials.
   */
  public function isAuthorized() {
    try {
      $response = $this->_getQueue();
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      // @todo better handling
      return FALSE;
    }
    return $response->getStatusCode() == 200;
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
    $urls_to_clear = [];
    global $base_url;
    foreach ($urls as $path) {
      if ($path[0] === '/') {
        $path = ltrim($path, '/');
      }
      $full_path = $base_url . '/' . $path;
      $url = Url::fromUserInput('/' . trim($path));
      try {
        if ($url->isRouted() && UrlHelper::isValid($full_path)) {
          $urls_to_clear[] = trim($path);
        }
        else {
          throw new \InvalidArgumentException($path . ' is invalid URLs');
        }
      }
      catch (InvalidArgumentException $e) {
        $this->logger->error($e->getMessage());
      }
    }
    return $this->purgeRequest($urls_to_clear);
  }

  /**
   * Ask the API to purge an object.
   *
   * @param array $objects
   *   A non-associative array of Akamai objects to clear.
   *
   * @return GuzzleHttp\Psr7\Response
   *    Response to purge request.
   *
   * @link https://developer.akamai.com/api/purge/ccu/reference.html
   * @link https://github.com/akamai-open/api-kickstart/blob/master/examples/php/ccu.php#L58
   */
  protected function purgeRequest($objects) {
    try {
      $response = $this->request(
        'POST',
        $this->apiBaseUrl . 'queues/' . $this->queue,
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
      //  }.
      return $response;
    }
    catch (ClientException $e) {
      $this->logger->error($this->formatExceptionMessage($e));
      // @todo better error handling
      // Throw $e;.
    }
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
      'action' => $this->action,
      'domain' => $this->domain,
      'type' => $this->type,
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
  public function getQueue($queue_name = 'default') {
    return Json::decode($this->_getQueue($queue_name)->getBody());
  }

  /**
   * Gets the raw Guzzle result of checking a queue.
   *
   * We use this to check connectivity, which is why it is broken out into a
   * private function.
   *
   * @param string $queue_name
   *   The queue name to check. Defaults to 'default'.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  private function _getQueue($queue_name = 'default') {
    return $this->get("/ccu/v2/queues/{$queue_name}");
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
    $request = new Request(
      $this->apiBaseUrl . 'purges/' . $purge_id
    );
    try {
      $response = $this->request($request);
      return $response;
    }
    catch (ClientException $e) {
      // @todo Better handling
      $this->logger->log($this->formatExceptionMessage($e));
      return FALSE;
    }
  }

  /**
   * Sets the queue.
   *
   * @param string $queue
   */
  public function setQueue($queue) {
    $this->queue = $queue;
  }

  /**
   * Helper function to set the action for purge request.
   *
   * @param string $action
   */
  public function setAction($action) {
    $valid_actions = array('remove', 'invalidate');
    if (in_array($action, $valid_actions)) {
      $this->action = $action;
    }
    else {
      throw new \InvalidArgumentException('Action must be one of: ' . implode(', ', $valid_actions));
    }
  }

  /**
   * Helper function to set type of the request.
   *
   * @param string $type
   */
  public function setType($type) {
    $valid_types = array('cpcode', 'arl');
    if (in_array($type, $valid_types)) {
      $this->type = $type;
    }
    else {
      throw new \InvalidArgumentException('Type must be one of: ' . implode(', ', $valid_types));
    }
  }

  /**
   * Sets the domain for purging data.
   *
   * @param string $domain
   */
  public function setDomain($domain) {
    $valid_domains = array('staging', 'production');
    if (in_array($domain, $valid_domains)) {
      $this->domain = $domain;
    }
    else {
      throw new \InvalidArgumentException('Domain must be one of: ' . implode(', ', $valid_domains));
    }
  }

  /**
   * Helper function to throw the exception.
   */
  protected function formatExceptionMessage(ClientException $e) {
    // Get the full response to avoid truncation.
    // @see https://laracasts.com/discuss/channels/general-discussion/guzzle-error-message-gets-truncated
    $error_detail = Json::decode($e->getResponse()->getBody()->getContents());
    $message = '';
    foreach ($error_detail as $key => $value) {
      $message .= "$key: $value " . PHP_EOL;
    }
    return $message;
  }

}
