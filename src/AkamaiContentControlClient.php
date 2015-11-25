<?php
/**
 * @file
 * Contains Drupal\akamai\AkamaiContentControlClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;


/**
 * Provides a service to interact with the Akamai Content Control REST API.
 */
class AkamaiContentControlClient implements AkamaiContentControlInterface {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The akamai.settings config object.
   *
   * @var \Drupal\Core\Config\Config;
   */
  protected $config;

  /**
   * Constructs an AkamaiContentControlClient object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   *
   * @todo Inject HTTPClient
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->config = $config_factory->get('akamai.settings');
    $this->logger = $logger;
    $this->httpClient = new AkamaiAuthentication($this->config);
  }

  /**
   * {@inheritdoc}
   */
  public function clearUrl($url) {
    $this->purgeUrl($url);
  }

  /**
   * Removes an object, based on URL path, from the Akamai cache.
   *
   * @param string $url
   *   The URL of the cached object to purge.
   *
   * @todo Incorporate invalidation as well as removing objects.
   */
  protected function purgeUrl($url) {
    // Set up parameters for the request. Note, arl requests define cache
    // objects by URL.
    $parameters = array(
      'type' => 'arl',
      'action' => 'remove',
      'domain' => $this->config->get('akamai_domain'),
      'objects' => array(
        $url,
      ),
    );

    // Use the devel endpoint if enabled.
    $endpoint = $this->config->get('akamai_devel_mode') ? $this->config->get('akamai_mock_endpoint') : $this->config->get('akamai_restapi_endpoint');

    $request = new Request('POST',
      $endpoint,
      $parameters
    );

    try {
     $response = $this->httpClient->send($request);
    }
    catch (RequestException $e) {
      // @todo Log/notify these more cleanly.
      $this->logger->error('There was a problem calling the Akamai CCU service.');
      $this->logger->error($e->getResponse());
      return;
    }

  }

}
