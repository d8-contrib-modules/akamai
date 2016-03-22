<?php
/**
 * @file
 * Contains \Drupal\akamai\AkamaiClient.
 */

namespace Drupal\akamai;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;

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
   * The domain for which Akamai is managing cache.
   */
  protected $baseUrl;

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

    $this
      // Set action to take based on configuration.
      ->setAction(key(array_filter($this->drupalConfig->get('action'))))
      // Set domain (staging or production).
      ->setDomain(key(array_filter($this->drupalConfig->get('domain'))))
      // Set base url for the cache (eg, example.com).
      ->setBaseUrl($this->drupalConfig->get('basepath'));

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
   *
   * @return bool
   *   TRUE if authorised, FALSE if not.
   */
  public function isAuthorized() {
    try {
      $response = $this->doGetQueue();
    }
    catch (RequestException $e) {
      // @todo better handling
      $this->logger->error($this->formatExceptionMessage($e));
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
    $urls = $this->normalizeUrls($urls);
    foreach ($urls as $url) {
      if ($this->isAkamaiManagedUrl($url) === FALSE) {
        throw new \InvalidArgumentException("The URL $url is not managed by Akamai. Try setting your Akamai base url.");
      }
    }
    return $this->purgeRequest($urls);
  }

  /**
   * Ask the API to purge an object.
   *
   * @param string[] $objects
   *   A non-associative array of Akamai objects to clear.
   *
   * @return \GuzzleHttp\Psr7\Response|bool
   *    Response to purge request, or FALSE on failure.
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
    catch (RequestException $e) {
      $this->logger->error($this->formatExceptionMessage($e));
      return FALSE;
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
  public function createPurgeBody($urls) {
    return [
      'objects' => $urls,
      'action' => $this->action,
      'domain' => $this->domain,
      'type' => $this->type,
    ];
  }

  /**
   * Given a list of URLs, ensure they are fully qualified.
   *
   * @param string[] $urls
   *   A list of URLs.
   *
   * @return string[]
   *   A list of fully qualified URls.
   */
  public function normalizeUrls($urls) {
    foreach ($urls as &$url) {
      $url = $this->normalizeUrl($url);
    }
    return $urls;
  }

  /**
   * Given a URL, make sure it is fully qualified.
   *
   * @param string $url
   *   A URL or Drupal path.
   *
   * @return string
   *   A fully qualified URL.
   */
  public function normalizeUrl($url) {
    if (UrlHelper::isExternal($url, TRUE)) {
      return $url;
    }
    else {
      // Otherwise, try prepending the base URL.
      $url = ltrim($url, '/');
      $domain = rtrim($this->baseUrl, '/');
      return $domain . '/' . $url;
    }
  }

  /**
   * Checks whether a fully qualified URL is handled by Akamai.
   *
   * Note this is based only on local config and doesn't check upstream.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if a url with an Akamai managed domain, FALSE if not.
   */
  public function isAkamaiManagedUrl($url) {
    return strpos($url, $this->baseUrl) !== FALSE;
  }

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
    return $this->get($this->apiBaseUrl . "queues/{$queue_name}");
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
   * Returns the status of a previous purge request.
   *
   * @param string $purge_id
   *   The UUID of the purge request to check.
   *
   * @return \GuzzleHttp\Psr7\Response|bool
   *    Response to purge status request, or FALSE on failure.
   */
  public function getPurgeStatus($purge_id) {
    try {
      $response = $this->request(
        'GET',
        $this->apiBaseUrl . 'purges/' . $purge_id
      );
      return $response;
    }
    catch (RequestException $e) {
      // @todo Better handling
      $this->logger->error($this->formatExceptionMessage($e));
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
   *
   * @return $this
   */
  public function setAction($action) {
    $valid_actions = array('remove', 'invalidate');
    if (in_array($action, $valid_actions)) {
      $this->action = $action;
    }
    else {
      throw new \InvalidArgumentException('Action must be one of: ' . implode(', ', $valid_actions));
    }
    return $this;
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
   * Sets Akamai base url.
   *
   * @param string $url
   *   The base url of the site Akamai is managing, eg 'http://example.com'.
   *
   * @return $this
   */
  public function setBaseUrl($url) {
    $this->baseUrl = $url;
    return $this;
  }

  /**
   * Sets API base url.
   *
   * @param string $url
   *   A url to an API, eg '/ccu/v2/'.
   *
   * @return $this
   */
  public function setApiBaseUrl($url) {
    $this->apiBaseUrl = $url;
    return $this;
  }

  /**
   * Formats a JSON error response into a string.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   The RequestException containing the JSON error response.
   *
   * @return string
   *   The formatted error message as a string.
   */
  protected function formatExceptionMessage(RequestException $e) {
    $message = '';
    // Get the full response to avoid truncation.
    // @see https://laracasts.com/discuss/channels/general-discussion/guzzle-error-message-gets-truncated
    if ($e->hasResponse()) {
      $body = $e->getResponse()->getBody();
      $error_detail = Json::decode($body);
      if (is_array($error_detail)) {
        foreach ($error_detail as $key => $value) {
          $message .= "$key: $value " . PHP_EOL;
        }
      }
    }
    // Fallback to the standard message.
    else {
      $message = $e->getMessage();
    }

    return $message;
  }

}
