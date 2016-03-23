<?php
/**
 * @file
 * Contains Drupal\akamai\Form\ConfigForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * A configuration form to interact with Akamai API settings.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'akamai.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'akamai_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('akamai.settings');

    // @todo decide whether we want a global killswitch here.
    $form = array();

    // Link to instructions on how to get Akamai credentials from Luna.
    $luna_url = 'https://developer.akamai.com/introduction/Prov_Creds.html';
    $luna_uri = Url::fromUri($luna_url);

    $form['akamai_credentials_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Akamai CCUv2 Credentials'),
      '#description' => $this->t('API Credentials for Akamai. Someone with Luna access will need to set this up. See @link for more.', array('@link' => $this->l($luna_url, $luna_uri))),
    );

    $form['akamai_credentials_fieldset']['rest_api_url'] = array(
      '#type' => 'url',
      '#title' => $this->t('REST API URL'),
      '#description'   => $this->t('The URL of the Akamai CCUv2 API host. It should be in the format *.purge.akamaiapis.net/'),
      '#default_value' => $config->get('rest_api_url'),
    );

    $form['akamai_credentials_fieldset']['access_token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description'   => $this->t('Access token.'),
      '#default_value' => $config->get('access_token'),
    );

    $form['akamai_credentials_fieldset']['client_token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client Token'),
      '#description'   => $this->t('Client token.'),
      '#default_value' => $config->get('client_token'),
    );

    $form['akamai_credentials_fieldset']['client_secret'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description'   => $this->t('Client secret.'),
      '#default_value' => $config->get('client_secret'),
    );

    global $base_url;
    $basepath = $config->get('basepath') ?: $base_url;

    $form['basepath'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Base Path'),
      '#default_value' => $basepath,
      '#description' => $this->t('The URL of the base path (fully qualified domain name) of the site.  This will be used as a prefix for all cache clears (Akamai indexes on the full URI). e.g. "http://www.example.com"'),
      '#required' => TRUE,
    );

    $form['timeout'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Timeout Length'),
      '#description' => $this->t("The timeout in seconds used when sending the cache clear request to Akamai's servers. Most users will not need to change this value."),
      '#size' => 5,
      '#maxlength' => 3,
      '#default_value' => $config->get('timeout'),
      '#required' => TRUE,
    );

    $form['domain'] = array(
      '#type' => 'select',
      '#title' => $this->t('Domain'),
      '#default_value' => $this->getMappingKey($config->get('domain')),
      '#options' => array(
        'production' => $this->t('Production'),
        'staging' => $this->t('Staging'),
      ),
      '#description' => $this->t('The Akamai domain to use for cache clearing.'),
      '#required' => TRUE,
    );

    $form['action'] = array(
      '#type' => 'select',
      '#title' => $this->t('Clearing Action Type Default'),
      '#default_value' => $this->getMappingKey($config->get('action')),
      '#options' => array(
        'remove' => $this->t('Remove'),
        'invalidate' => $this->t('Invalidate'),
      ),
      '#description' => $this->t('The default clearing action. The options are <em>remove</em> (which removes the item from the Akamai cache) and <em>invalidate</em> (which leaves the item in the cache, but invalidates it so that the origin will be hit on the next request).'),
      '#required' => TRUE,
    );

    $form['status_expire'] = array(
      '#type' => 'textfield',
      '#title' => t('Purge Status expiry'),
      '#default_value' => $config->get('status_expire'),
      '#description' => $this->t('This module keeps a log of purge statuses. They are automatically deleted after this amount of time (in seconds).'),
      '#size' => 12,
    );

    $form['devel_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => t('Development Options'),
    );

    $form['devel_fieldset']['log_requests'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Log requests'),
      '#default_value' => $config->get('log_requests'),
      '#description' => $this->t('Log all requests and responses.'),
    );

    $form['devel_fieldset']['devel_mode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use development mode'),
      '#default_value' => $config->get('devel_mode'),
      '#description' => $this->t('Use a Mock API instead of a live one.'),
    );

    $form['devel_fieldset']['mock_endpoint'] = array(
      '#type' => 'url',
      '#size' => 100,
      '#title' => $this->t('Mock endpoint URI'),
      '#default_value' => $config->get('mock_endpoint'),
      '#description' => $this->t('Mock endpoint used in development mode'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $int_fields = array('timeout', 'status_expire');
    foreach ($int_fields as $field) {
      if (!ctype_digit($form_state->getValue($field))) {
        $form_state->setErrorByName($field, $this->t('Please enter only integer values in this field.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitform(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('akamai.settings')
      ->set('rest_api_url', $values['rest_api_url'])
      ->set('client_token', $values['client_token'])
      ->set('client_secret', $values['client_secret'])
      ->set('access_token', $values['access_token'])
      ->set('basepath', $values['basepath'])
      ->set('timeout', $values['timeout'])
      ->set('status_expire', $values['status_expire'])
      ->set('domain', $this->saveDomain($values['domain']))
      ->set('action', $this->saveAction($values['action']))
      ->set('devel_mode', $values['devel_mode'])
      ->set('mock_endpoint', $values['mock_endpoint'])
      ->set('log_requests', $values['log_requests'])
      ->save();

    $this->checkCredentials();
    drupal_set_message($this->t('Settings saved.'));
  }

  /**
   * Ensures credentials supplied actually work.
   */
  protected function checkCredentials() {
    $client = \Drupal::service('akamai.edgegridclient');
    if ($client->isAuthorized()) {
      drupal_set_message('Authenticated to Akamai.');
    }
    else {
      drupal_set_message('Akamai authentication failed.', 'error');
    }
  }

  /**
   * Return the key of the active selection in a domain or action mapping.
   *
   * @param array $array
   *   A settings array corresponding to a mapping with booleans against keys.
   *
   * @return mixed
   *   The key of the first value with boolean TRUE.
   */
  protected function getMappingKey($array) {
    return key(array_filter($array));
  }

  /**
   * Converts a form value for 'domain' back to a saveable array.
   *
   * @param string $value
   *   The value submitted via the form.
   *
   * @return array
   *   An array suitable for saving back to config.
   */
  protected function saveDomain($value) {
    $domain = array(
      'production' => FALSE,
      'staging' => FALSE,
    );

    $domain[$value] = TRUE;
    return $domain;
  }

  /**
   * Converts a form value for 'action' back to a saveable array.
   *
   * @param string $value
   *   The value submitted via the form.
   *
   * @return array
   *   An array suitable for saving back to config.
   */
  protected function saveAction($value) {
    $action = array(
      'remove' => FALSE,
      'invalidate' => FALSE,
    );

    $action[$value] = TRUE;
    return $action;
  }

}
