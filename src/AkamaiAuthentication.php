<?php


namespace Drupal\akamai;

use Drupal\Core\Config\ConfigFactoryInterface;
use Akamai\Open\EdgeGrid\Authentication;

/**
 * Connects to the Akamai EdgeGrid.
 *
 * Akamai's PHP Client library expects an authentication object which it then
 * integrates with a Guzzle client to create signed requests. This class
 * integrates Drupal configuration with that Authentication class, so that
 * standard Drupal config patterns can be used.
 */
class AkamaiAuthentication extends Authentication {

  /**
   * AkamaiAuthentication constructor.
   *
   * @param \Drupal\Core\Config\Config $config
   *   A config object, containing client authentication details.
   */
  public function __construct(ConfigFactoryInterface $config) {

    // Set the auth credentials up.
    // @see Authentication::createFromEdgeRcFile()
    $this->setAuth(
      $config->get('client_token'),
      $config->get('client_secret'),
      $config->get('access_token')
    );

    // Set the upstream API host.

    // @todo Maybe make the devel mode check a library function?
    if ($this->config->get('akamai_devel_mode') == TRUE) {
      $this->setHost($config->get('akamai_mock_endpoint'));
    }

  }

}
