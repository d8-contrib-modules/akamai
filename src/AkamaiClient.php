<?php
/**
 * @file
 * Contains \Drupal\akamai\AkamaiClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Client;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\akamai\StatusStorage;

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
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * A purge status logger.
   *
   * @var StatusStorage
   */
  protected $statusStorage;

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
   * @param \Drupal\akamai\StatusStorage $status_storage
   *   A status logger for tracking purge responses.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, StatusStorage $status_storage) {
    $this->logger = $logger;
    $this->drupalConfig = $config_factory->get('akamai.settings');
    $this->akamaiClientConfig = $this->createClientConfig();
    $this->statusStorage = $status_storage;

    // Set action to take based on configuration.
    $this->setAction(key(array_filter($this->drupalConfig->get('action'))));
    $this->setDomain(key(array_filter($this->drupalConfig->get('domain'))));

    // Create an authentication object so we can sign requests.
    $auth = AkamaiAuthentication::create($config_factory);
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
  public function createClientConfig() {
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
      $response = $this->doGetQueue();
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
   * @return \GuzzleHttp\Psr7\Response
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
   * @return \GuzzleHttp\Psr7\Response
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
   *
   * @return \GuzzleHttp\Psr7\Response
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
      // @code
      // {
      //  "estimatedSeconds": 420,
      //  "progressUri": "/ccu/v2/purges/57799d8b-10e4-11e4-9088-62ece60caaf0",
      //  "purgeId": "57799d8b-10e4-11e4-9088-62ece60caaf0",
      //  "supportId": "17PY1405953363409286-284546144",
      //  "httpStatus": 201,
      //  "detail": "Request accepted.",
      //  "pingAfterSeconds": 420
      //  }.
      // @endcode
      $this->statusStorage->saveResponseStatus($response, $objects);
      return $response;
    }
    catch (ClientException $e) {
      $this->logger->error($this->formatExceptionMessage($e));
      // @todo better error handling
      // Throw $e;.
    }
  }

  /**
   * Create an array to pass to Akamai's purge function.
   *
   * @param string[] $urls
   *   A list of URLs.
   *
   * @return array
   *   An array suitable for sending to the Akamai purge endpoint.
   */
  protected function createPurgeBody($urls) {
    // Append the basepath to all URLs. Akamai only accepts fully formed URLs.
    foreach ($urls as &$url) {
      $url = $this->drupalConfig->get('basepath') . '/' . $url;
    }
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
   * @return array
   *    Response body of request as associative array.
   *
   * @link https://api.ccu.akamai.com/ccu/v2/docs/#section_CheckingQueueLength
   * @link https://developer.akamai.com/api/purge/ccu/reference.html
   */
  public function getQueue($queue_name = 'default') {
    return Json::decode($this->doGetQueue($queue_name)->getBody());
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
   *   The HTTP response.
   */
  private function doGetQueue($queue_name = 'default') {
    return $this->get("/ccu/v2/queues/{$queue_name}");
  }

  /**
   * Get the number of items remaining in the purge queue.
   *
   * @return int
   *   A count of the remaining items in the purge queue.
   */
  public function getQueueLength() {
    return $this->getQueue()['queueLength'];
  }

  /**
   * Return the status of a previous purge request.
   *
   * @param string $purge_id
   *   The UUID of the purge request to check.
   */
  public function getPurgeStatus($purge_id) {
    try {
      $response = $this->request(
        'GET',
        $this->apiBaseUrl . 'purges/' . $purge_id
      );
      return $response;
    }
    catch (ClientException $e) {
      // @todo Better handling
      $this->logger->log($this->formatExceptionMessage($e));
      return FALSE;
    }
  }

  /**
   * Sets the queue name.
   *
   * @param string $queue
   *   The queue name.
   *
   * @return $this
   */
  public function setQueue($queue) {
    $this->queue = $queue;
    return $this;
  }

  /**
   * Sets the type of purge.
   *
   * @param string $type
   *   The type of purge, either 'arl' or 'cpcode'.
   *
   * @return $this
   */
  public function setType($type) {
    $valid_types = array('cpcode', 'arl');
    if (in_array($type, $valid_types)) {
      $this->type = $type;
    }
    else {
      throw new \InvalidArgumentException('Type must be one of: ' . implode(', ', $valid_types));
    }
    return $this;
  }

  /**
   * Helper function to set the action for purge request.
   *
   * @param string $action
   *   Action to be taken while purging.
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
   * Sets the domain to clear.
   *
   * @param string $domain
   *   The domain to clear, either 'production' or 'staging'.
   *
   * @return $this
   */
  public function setDomain($domain) {
    $valid_domains = array('staging', 'production');
    if (in_array($domain, $valid_domains)) {
      $this->domain = $domain;
    }
    else {
      throw new \InvalidArgumentException('Domain must be one of: ' . implode(', ', $valid_domains));
    }
    return $this;
  }

  /**
   * Formats a JSON error response into a string.
   *
   * @param \GuzzleHttp\Exception\ClientException $e
   *   The ClientException containing the JSON error response.
   *
   * @return string
   *   The formatted error message as a string.
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

  /**
   * Removes invalid URLs from an array of URLs.
   *
   * @param string[] $urls
   *   Array of URLs.
   *
   * @return string[]
   *   Array of valid URLs to purge.
   */
  public function removeInvalidUrls($urls) {
    $urls_to_clear = [];
    $base_url = $this->drupalConfig->get('basepath');
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
          throw new \InvalidArgumentException($path . ' is a not a URL handled by this Drupal site. Please provide a valid URL for purging.');
        }
      }
      catch (\InvalidArgumentException $e) {
        $this->logger->error($e->getMessage());
      }
    }
    return $urls_to_clear;
  }

}
