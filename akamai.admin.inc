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

  $form['disable_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => 'Disable Akamai Cache Clearing',
    '#description' => 'Set this field to temporarity disable cache clearing during imports, migrations, or other batch processes.',
  );

  $form['disable_fieldset']['akamai_disabled'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Disable cache clearing'),
    '#default_value' => variable_get('akamai_disabled', FALSE),
  );

  $form['akamai_restapi'] = array(
    '#type'          => 'textfield',
    '#title'         => t('REST API URL'),
    '#default_value' => variable_get('akamai_restapi', 'https://api.ccu.akamai.com/ccu/v2/queues/default'),
    '#description'   => t('The URL of the Akamai REST API call e.g. "https://api.ccu.akamai.com/ccu/v2/queues/default"'),
    '#required'      => TRUE,
  );

  $form['akamai_basepath'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Base Path'),
    '#default_value' => variable_get('akamai_basepath', ''),
    '#description'   => t('The URL of the base path (fully qualified domain name) of the site.  This will be used as a prefix for all cache clears (Akamai indexs on the full URI). e.g. "http://www.example.com"'),
    '#required'      => TRUE,
  );

  $form['akamai_timeout'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Timeout Length'),
    '#description'   => t("The timeout used by when sending the cache clear request to Akamai's servers. Most users will not need to change this value."),
    '#size'          => 5,
    '#maxlength'     => 3,
    '#default_value' => variable_get('akamai_timeout', '5'),
    '#required'      => TRUE,
  );

  $form['akamai_username'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Cache clearing user'),
    '#default_value' => variable_get('akamai_username', ''),
    '#description'   => t('The user name of the account being used for cache clearing (most likely an email)'),
    '#required'      => TRUE,
  );

  if (variable_get('akamai_password', '')) {
    $password_status_text = t('Akamai CCU Password is set.  Use the fields below to change or leave blank to use the existing password.');
  }
  else {
    $password_status_text = t('Your Akamai CCU Password is not set.  Please set it using the fields below.');
  }

  $form['password_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => 'Akamai CCU Password',
    '#description' => $password_status_text,
  );

  $form['password_fieldset']['akamai_password'] = array(
    '#type'          => 'password_confirm',
    '#title'         => t('Cache clearing password'),
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
    '#description'   => t('The Akamai domain to use for cache clearing'),
    '#required'      => TRUE,
  );

  $form['akamai_action'] = array(
    '#type'          => 'select',
    '#title'         => t('Clearing Action Type Default'),
    '#default_value' => variable_get('akamai_action', 'remove'),
    '#options'       => array(
      'remove'     => t('Remove'),
      'invalidate' => t('Invalidate'),
    ),
    '#description'   => t('The default clearing action.  The options are <em>remove</em> (which removes the item from the Akamai cache) and <em>invalidate</em> (which leaves the item in the cache, but invalidates it so that the origin will be hit on the next request)'),
    '#required'      => TRUE,
  );

  return system_settings_form($form);
}

/**
 * Validate the akamai_settings form.
 *
 */
function akamai_settings_validate($form, &$form_state) {
  // verify restapi and basepath are valid absolute URLs
  foreach (array('akamai_basepath' => 'Base Path', 'akamai_restapi' => 'REST API URL') as $field => $field_title) {
    if (!valid_url($form_state['values'][$field], TRUE)) {
      form_set_error($field, t("The %field_title value must be a valid, absolute URL", array('%field_title' => $field_title)));
    }
  }

  // If the password field is not set, remove it from the values array.
  // This prevents the value from being overwritten with an empty string.
  if (!$form_state['values']['akamai_password']) {
    unset($form_state['values']['akamai_password']);
  }

  // check if timeout value is an integer
  $filtered_akamai_timeout = filter_var(
    $form_state['values']['akamai_timeout'],
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1)));
  if (!$filtered_akamai_timeout) {
    form_set_error('akamai_timeout', 'The Timeout Length must be an integer greater than 0.');
  }
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
    'domain' => $form_state['values']['domain_override']
  );

  if ($result = akamai_clear_url($paths, $overrides)) {
    $message = t("Akamai Cache Request has been made successfully.") . theme("item_list", $paths);
    $status = 'status';

    if (is_object($result) && !empty($result->data)) {
      if ($akamai_response_data = json_decode($result->data)) {
        if (isset($akamai_response_data->httpStatus) && $akamai_response_data->httpStatus > 300) {
          $message = t("There was a problem with your cache clearing request.  The error message returned was '@msg'",
            array('@msg' => $akamai_response_data->details));
          $status = 'error';
        }
        else {
          $message = t("Akamai Cache Request has been made successfully.  Akamai reports an estimated time to completion of @time",
            array('@time' => format_interval($akamai_response_data->estimatedSeconds))) . theme("item_list", $paths);
        }
      }
      else {
        $message = t('There was a problem with your cache clearing request. Please check the watchdog logs for details.');
        $status = 'error';

        watchdog('akamai', 'Unable to parse Akamai API response data: @json_data',
          array('@json_data' => '<pre>' . print_r($result->data, TRUE) . '</pre>'), WATCHDOG_ERROR);
      }
    }
  }
  else {
    $message = t('There was a problem with your cache clearing request. Please check the watchdog logs for details.');
    $status = 'error';
  }

  drupal_set_message($message, $status);
}

