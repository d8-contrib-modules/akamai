<?php

/**
 * @file
 * Contains Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger.
 */

namespace Drupal\akamai\Plugin\Purge\Purger;


use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;

/**
 * Akamai Purger.
 *
 * @PurgePurger(
 *   id = "akamai",
 *   label = @Translation("Akamai Purger"),
 *   description = @Translation("Provides a Purge service for Akamai CCU."),
 *   enable_by_default = false,
 *   configform = "",
 * )
 */
class AkamaiPurger extends PurgerBase implements PurgerInterface {
  /**
   * @inheritDoc
   */
  public function getTimeHint() {
    // @todo: Implement getTimeHint() method.
  }

  /**
   * @inheritDoc
   */
  public function invalidate(array $invalidations) {
    // @todo: Implement invalidate() method.
  }

}
