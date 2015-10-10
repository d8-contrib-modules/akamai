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
   * Purges a list of URLs from the edge cache.
   *
   * @param array $urls
   *   A list of one or more URLs to purge.
   */
  public function clearUrls(array $urls);

  /**
   * Purges a single URL from the edge cache.
   *
   * @param string $url
   *   A single URL.
   */
  public function clearUrl($url);

}
