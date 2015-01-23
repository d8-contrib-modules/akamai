<?php

/**
 * Implements hook_drush_command().
 */
function akamai_drush_command() {
  $items = array();

  $items['akamai-clear-url'] = array(
    'description' => 'Akamai clear url.',
    'arguments' => array(
      'path' => 'A path to clear. You can provide as many paths you like.',
    ),
    'options' => array(
      'action' => 'Clearing action type.',
      'domain' => 'The Akamai domain to use for cache clearing.',
    ),
    'aliases' => array('akcu'),
    'callback' => 'akamai_drush_clear_url',
  );

  return $items;
}

/**
 * Callback function for ak-clear-url command.
 *
 * Runs the akamai clear url command
 */
function akamai_drush_clear_url() {
  $paths = func_get_args();
  $overrides = array();
  if (drush_get_option('action')) {
    $overrides['action'] = drush_get_optons('action');
  }
  if (drush_get_option('domain')) {
    $overrides['domain'] = drush_get_optons('domain');
  }
  $did_clear = akamai_clear_url($paths, $overrides, NULL);
  if ($did_clear) {
    $message = t("Akamai Cache Request has been made successfully, please allow 10 minutes for changes to take effect.\n") . print_r($paths, TRUE);
    drush_print("$message\n");
  }
  else {
    drush_set_error(t("There was a problem with your cache control request."));
  }
}
