<?php

/**
 * @file
 * Contains \Drupal\akamai\Plugin\Block\CacheClearBlock.
 */

namespace Drupal\akamai\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Provides a block to clear the currently viewed URL.
 *
 * @Block(
 *   id = "akamai_cache_clear_block",
 *   admin_label = @Translation("Akamai Cache Clear"),
 *   category = @Translation("Akamai")
 * )
 */
class CacheClearBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Form Builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new CacheClearBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Form Builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = $this->formBuilder->getForm('Drupal\akamai\Form\ClearUrlForm');
    return array(
      'cache_clear_form' => $form,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'purge akamai cache');
  }

}
