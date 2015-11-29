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
    $this->logger = $logger;
    $this->httpClient = AkamaiClient::create($config_factory);
  }


  /**
   * Removes an object, based on URL path, from the Akamai cache.
   *
   * @param string $url
   *   The URL of the cached object to purge.
   *
   * @todo Incorporate invalidation as well as removing objects.
   */
  public function purgeUrl($url) {
    $this->httpClient->purgeUrl($url);
  }

}
