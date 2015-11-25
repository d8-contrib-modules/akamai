<?php

/**
 * @file
 * Contains Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger.
 */

namespace Drupal\akamai\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\akamai\AkamaiClient;

/**
 * Akamai Purger.
 *
 * @PurgePurger(
 *   id = "akamai",
 *   label = @Translation("Akamai Purger"),
 *   description = @Translation("Provides a Purge service for Akamai CCU."),
 *   types = {"url", "everything"},
 *   configform = "Drupal\akamai\Form\AkamaiConfigForm",
 * )
 */
class AkamaiPurger extends PurgerBase implements PurgerInterface {


  // Web services client.
  protected $client;

  function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->client = new AkamaiClient($configuration);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * @inheritDoc
   */
  public function getTimeHint() {
    // @todo Create a configurable max timeout.
    return 4.00;
  }

  /**
   * @inheritDoc
   */
  public function invalidate(array $invalidations) {
    // @todo: Implement invalidate() method.
  }


  protected function instantiateClient() {

  }

}
