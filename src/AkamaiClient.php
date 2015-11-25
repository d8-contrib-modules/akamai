<?php


namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Client;

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
  protected $akamai_client_config;


  /**
   * AkamaiAuthentication constructor.
   *
   * @param \Drupal\Core\Config\Config $config
   *   A config object, containing client authentication details.
   */
  public function __construct(ConfigFactoryInterface $config) {

    $this->config = $config;

    $this->akamai_client_config = array();
    $this->createClientConfig();

    $authentication = new AkamaiAuthentication($config);

    // @see Client::createFromEdgeRcFile()
    $this->setAuth(
      $config->get('client_token'),
      $config->get('client_secret'),
      $config->get('access_token')
    );

    parent::__construct($this->akamai_client_config, $authentication);
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
    // If we are in devel mode, use the mocked endpoint.
    if ($this->config->get('akamai_devel_mode') == TRUE) {
      $this->akamai_client_config['base_uri'] = $this->config->get('akamai_mock_endpoint');
    }

    $this->akamai_client_config['timeout'] = $this->config->get('akamai_timeout');

    return $this->akamai_client_config;
  }

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
