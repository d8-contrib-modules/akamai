<?php

/**
 * @file
 * Contains \Drupal\akamai\Plugin\Block\AkamaiCacheClearBlock.
 */

namespace Drupal\akamai\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block to clear the currently viewed URL.
 *
 * @Block(
 *   id = "akamai_cache_clear_block",
 *   admin_label = @Translation("Akamai Cache Clear"),
 *   category = @Translation("Akamai")
 * )
 */
class AkamaiCacheClearBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\akamai\Form\AkamaiClearUrlForm');
    return array(
      'cache_clear_form' => $form,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return $account->hasPermission('purge akamai cache');
  }

}
