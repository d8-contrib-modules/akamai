<?php
/**
 * @file
 * Contains Drupal\akamai\Form\AkamaiConfigForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A configuration form to interact with Akamai API settings.
 */
class AkamaiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'akamai.settings'
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

    $form = array();

    $form['disable_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Disable Akamai Cache Clearing'),
      '#description' => $this->t('Set this field to disable cache clearing during imports, migrations, or other batch processes.'),
    );

    $form['disable_fieldset']['disabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Disable cache clearing'),
      '#default_value' => $config->get('disable'),
    );

    $form['akamai_restapi_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('REST API Endpoint'),
      '#description'   => $this->t('The URL of the Akamai CCUv2 API host. It should be in the format *.luna.akamaiapis.net/'),
      '#default_value' => $config->get('rest_api_host'),
    );

    global $base_url;
    $basepath = $config->get('basepath') ?: $base_url;

    $form['basepath'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Base Path'),
      '#default_value' => $basepath,
      '#description' => $this->t('The URL of the base path (fully qualified domain name) of the site.  This will be used as a prefix for all cache clears (Akamai indexs on the full URI). e.g. "http://www.example.com"'),
      '#required' => TRUE,
    );

    $form['timeout'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Timeout Length'),
      '#description' => $this->t("The timeout used by when sending the cache clear request to Akamai's servers. Most users will not need to change this value."),
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
      '#description' => $this->t('The Akamai domain to use for cache clearing'),
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
      '#description' => $this->t('The default clearing action.  The options are <em>remove</em> (which removes the item from the Akamai cache) and <em>invalidate</em> (which leaves the item in the cache, but invalidates it so that the origin will be hit on the next request)'),
      '#required' => TRUE,
    );

    $form['devel_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => t('Development Options'),
    );

    $form['devel_fieldset']['devel_mode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use development mode'),
      '#default_value' => $config->get('devel_mode'),
      '#description' => $this->t('Use the a Mock API instead of a live one.'),
    );

    $form['devel_fieldset']['mock_endpoint'] = array(
      '#type' => 'textfield',
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
  public function submitform(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('akamai.settings')
      ->set('disabled', $values['disabled'])
      ->set('rest_api_endpoint', $values['rest_api_endpoint'])
      ->set('basepath', $values['basepath'])
      ->set('timeout', $values['timeout'])
      ->set('domain', $this->saveDomain($values['domain']))
      ->set('action', $this->saveAction($values['action']))
      ->set('devel_mode', $values['devel_mode'])
      ->set('mock_endpoint', $values['mock_endpoint'])
      ->save();

    drupal_set_message($this->t('Settings saved.'));
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
