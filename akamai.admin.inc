<?php

/**
 * @file
 *   Akamai is a registered trademark of Akamai Technologies, Inc.
 *   Administrative pages for the Akamai module.
 */

/**
 * General Settings for Akamai
 */
function akamai_settings() {
  $form = array();

  $form['akamai_restapi'] = array(
    '#type'          => 'textfield',
    '#title'         => t('REST API URL'),
    '#default_value' => variable_get('akamai_restapi', 'https://api.ccu.akamai.com/ccu/v2/queues/default'),
    '#description'   => t('The URL of the Akamai REST API call e.g. "https://api.ccu.akamai.com/ccu/v2/queues/default"')
  );

  $form['akamai_basepath'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Base Path'),
    '#default_value' => variable_get('akamai_basepath', ''),
    '#description'   => t('The URL of the base path (fully qualified domain name) of the site.  This will be used as a prefix for all cache clears (Akamai indexs on the full URI). e.g. "http://www.example.com"')
  );

  $form['akamai_username'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Cache clearing user'),
    '#default_value' => variable_get('akamai_username', ''),
    '#description'   => t('The user name of the account being used for cache clearing (most likely an email)')
  );

  $form['akamai_password'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Cache clearing password'),
    '#default_value' => variable_get('akamai_password', ''),
    '#description'   => t('The password of the cache clearing user')
  );

  $form['akamai_domain'] = array(
    '#type'          => 'select',
    '#title'         => t('Domain'),
    '#default_value' => variable_get('akamai_domain', 'staging'),
    '#options'       => array(
      'staging'    => t('Staging'),
      'production' => t('Production'),
    ),
    '#description'   => t('The Akamai domain to use for cache clearing')
  );

  $form['akamai_action'] = array(
    '#type'          => 'select',
    '#title'         => t('Clearing Action Type Default'),
    '#default_value' => variable_get('akamai_action', 'remove'),
    '#options'       => array(
      'remove'     => t('Remove'),
      'invalidate' => t('Invalidate'),
    ),
    '#description'   => t('The default clearing action.  The options are <em>remove</em> (which removes the item from the Akamai cache) and <em>invalidate</em> (which leaves the item in the cache, but invalidates it so that the origin will be hit on the next request)')
  );

  $form['akamai_email'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Email Notification Override'),
    '#default_value' => variable_get('akamai_email', ''),
    '#description'   => t('If this email address is specified all cache clearing requests will send notifications to this address.  If this address is not specified, the email address of the user executing the request will be used.')
  );
  return system_settings_form($form);

}
/**
 * General cache clearing page.
 */
function akamai_cache_control() {
  $form = array();

  $form['paths'] = array(
    '#type'        => 'textarea',
    '#title'       => t('Paths/URLs'),
    '#description' => t('Enter one URL per line. URL entries should be relative to the basepath. (e.g. node/1, content/pretty-title, sites/default/files/some/image.png'),
  );

  $form['domain_override'] = array(
    '#type'          => 'select',
    '#title'         => t('Domain'),
    '#default_value' => variable_get('akamai_domain', 'staging'),
    '#options'       => array(
      'staging'    => t('Staging'),
      'production' => t('Production'),
    ),
    '#description'   => t('The Akamai domain to use for cache clearing.  Defaults to the Domain setting from the settings page.')
  );

  $form['refresh'] = array(
    '#type'        => 'radios',
    '#title'       => t('Clearing Action Type'),
    '#default_value' => variable_get('akamai_action', ''),
    '#options'     => array(
      'remove'     => t('Remove'),
      'invalidate' => t('Invalidate'),
    ),
    '#description' => t('<b>Remove:</b> Purge the content from Akamai edge server caches. The next time the edge server receives a request for the content, it will retrieve the current version from the origin server. If it cannot retrieve a current version, it will follow instructions in your edge server configuration.<br/><br/><b>Invalidate:</b> Mark the cached content as invalid. The next time the Akamai edge server receives a request for the content, it will send an HTTP conditional get (If-Modified-Since) request to the origin. If the content has changed, the origin server will return a full fresh copy; otherwise, the origin normally will respond that the content has not changed, and Akamai can serve the already-cached content.<br/><br/><b>Note that <em>Remove</em> can increase the load on the origin more than <em>Invalidate</em>.</b> With <em>Invalidate</em>, objects are not removed from cache and full objects are not retrieved from the origin unless they are newer than the cached versions.'),
  );

  $default_email = akamai_get_notification_email();
  $form['email'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Email Notification'),
    '#default_value' => $default_email,
    '#description'   => t('Email address to be used for cache clear notifications. Note that it can take up to 10 minutes or more to receive a notification.')
  );
  $form['submit'] = array(
    '#type'  => 'submit',
    '#value' => t('Start Refreshing Content'),
  );

  return $form;
}


/**
 * Submit handler for akamai_cache_control().
 *
 * Process the settings and submit to Akamai
 */
function akamai_cache_control_submit($form, &$form_state) {
  $paths = explode("\n", filter_xss($form_state['values']['paths']));
  $action = $form_state['values']['refresh'];

  $overrides = array(
    'action' => $form_state['values']['refresh'],
    'domain' => $form_state['values']['domain_override'],
    'email'  => $form_state['values']['email'],
  );

  $did_clear = akamai_clear_url($paths, $overrides);
  if ($did_clear) {
    $message = t("Akamai Cache Request has been made successfully, please allow 10 minutes for changes to take effect.") . theme("item_list", $paths);
    drupal_set_message($message);
  }
  else {
    drupal_set_message(t("There was a problem with your cache control request. Check your log messages for more information."), 'error');
  }
}

