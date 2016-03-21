<?php

/**
 * @file
 * Contains \Drupal\akamai\AkamaiAuthentication.
 */

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
   * AkamaiAuthentication factory method, following superclass patterns.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   A config factory, for getting client authentication details.
   *
   * @return \Drupal\akamai\AkamaiAuthentication
   *   An authentication object.
   */
  public static function create(ConfigFactoryInterface $config) {
    // Following the pattern in the superclass.
    $auth = new static();

    $config = $config->get('akamai.settings');
    // @todo Maybe make the devel mode check a library function?
    if ($config->get('devel_mode') == TRUE) {
      $auth->setHost($config->get('mock_endpoint'));
    }
    else {
      $auth->setHost($config->get('rest_api_url'));
      // Set the auth credentials up.
      // @see Authentication::createFromEdgeRcFile()
      $auth->setAuth(
        $config->get('client_token'),
        $config->get('client_secret'),
        $config->get('access_token')
      );
    }

    return $auth;
  }

  /**
   * Returns the auth config.
   *
   * @return string[]
   *   An array with keys client_token, client_secret, access_token.
   */
  public function getAuth() {
    return $this->auth;
  }

}
