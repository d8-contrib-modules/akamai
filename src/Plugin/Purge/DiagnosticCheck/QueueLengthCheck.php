<?php

namespace Drupal\akamai\Plugin\Purge\DiagnosticCheck;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\akamai\AkamaiClient;

/**
 * Checks if valid Api credentials have been entered for CloudFlare.
 *
 * @PurgeDiagnosticCheck(
 *   id = "akamai_queue_length",
 *   title = @Translation("Akamai - Queue Length"),
 *   description = @Translation("Reports on current length of Akamai queue."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"akamai"}
 * )
 */
class QueueLengthCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * The settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * An Akamai web services client.
   *
   * @var \Drupal\akamai\AkamaiClient
   */
  protected $akamaiClient;

  /**
   * Constructs a Akamai CredentialCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\akamai\AkamaiClient $akamai_client
   *   An Akamai web services client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config, AkamaiClient $akamai_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config->get('akamai.settings');
    $this->akamaiClient = $akamai_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      \Drupal::service('akamai.edgegridclient')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $length = $this->akamaiClient->getQueueLength();

    $this->recommendation = $length === 0 ?
      $this->t('Purging queue is empty.') :
      $this->formatPlural($length, '%count item in the queue', '%count items in the queue', ['%count' => $length]);

    return SELF::SEVERITY_OK;
  }

}
