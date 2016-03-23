<?php

/**
 * @file
 * Contains Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger.
 */

namespace Drupal\akamai\Plugin\Purge\Purger;

use Drupal\Component\Utility\UrlHelper;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;


/**
 * Akamai Purger.
 *
 * @PurgePurger(
 *   id = "akamai",
 *   label = @Translation("Akamai Purger"),
 *   description = @Translation("Provides a Purge service for Akamai CCU."),
 *   types = {"url", "everything"},
 *   configform = "Drupal\akamai\Form\ConfigForm",
 * )
 */
class AkamaiPurger extends PurgerBase implements PurgerInterface {


  /**
   * Web services client for Akamai API.
   *
   * @var \Drupal\akamai\AkamaiClient
   */
  protected $client;

  /**
   * Akamai client config.
   *
   * @var \Drupal\Core\Config;
   */
  protected $akamaiClientConfig;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * Constructs a \Drupal\Component\Plugin\AkamaiPurger.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = \Drupal::service('akamai.edgegridclient');
    $this->akamaiClientConfig = $config->get('akamai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {
    // The max value for getTimeHint is 10.00.
    $return = $this->akamaiClientConfig->get('timeout') <= 10 ?: 10;
    return (float) $return;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $invalidation_type = $invalidation->getPluginId();

      switch ($invalidation_type) {
        case 'url':
          // URL invalidations should be of type \Drupal\purge\Plugin\Purge\Invalidation\UrlInvalidation.
          try {
            // This SHOULD be an internal path, but in some cases, like
            // when a database is moved between environments, might not be.
            $url = $invalidation->getUrl()->getInternalPath();
          }
          catch (\UnexpectedValueException $e) {
            $url = $invalidation->getUrl()->getUri();
            // Parse out the path.
            $url = UrlHelper::parse($url);
            // @todo Investigate whether we need to build path, query, fragment.
            $url = $url['path'];
          }
          $urls_to_clear[] = $url;
          break;
      }
    }

    // Purge all URLs in a single request. Akamai accepts up to 50 (?)
    // invalidations per request.
    if ($this->client->purgeUrls($urls_to_clear)) {
      // Now mark all URLs as cleared.
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
      }
    }
  }

  /**
   * Use a static value for purge queuer performance.
   *
   * Akamai's CCUv2 can take several minutes to action a purge request. However,
   * from Purge's perspective, we should mark objects as purged upstream when
   * Akamai accepts them for purging.
   *
   * @todo investigate whether we can track performance asynchronously.
   *
   * @see parent::hasRunTimeMeasurement()
   */
  public function hasRuntimeMeasurement() {
    return FALSE;
  }

}
