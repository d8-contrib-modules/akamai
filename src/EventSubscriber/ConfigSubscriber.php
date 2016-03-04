<?php

/**
 * @file
 * Contains \Drupal\akamai\EventSubscriber\ConfigSubscriber.
 */

namespace Drupal\akamai\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for config changes to Akamai credentials.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * Validates Akamai credentials upstream on config changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   A config change event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    // Check for changes to the Akamai config credentials, and validate them
    // with the upstream service.
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'akamai.settings') {
      if (
          $event->isChanged('rest_api_url') or
          $event->isChanged('client_token') or
          $event->isChanged('client_secret') or
          $event->isChanged('access_token')
      ) {
        \Drupal::state()->set('akamai.valid_credentials', \Drupal::service('akamai.edgegridclient')->isAuthorized());
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    $events[ConfigEvents::SAVE][] = array('onConfigSave', 0);
    return $events;
  }

}
