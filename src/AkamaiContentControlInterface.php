<?php

/**
 * @file
 * Contains \Drupal\akamai\AkamaiContentControl.
 */

namespace Drupal\akamai;

/**
 * Interface for an implementation of the Akamai cache control service.
 */
interface AkamaiContentControlInterface {

  /**
   * Purges a URL or list of URLs from the edge cache.
   *
   * @param string $url
   *   A single URL.
   */
  public function clearUrl($url);

}
