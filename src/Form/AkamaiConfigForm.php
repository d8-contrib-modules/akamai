<?php
/**
 * @file
 * Contains Drupal\akamai\Form\AkamaiConfigForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AkamaiConfigForm
 *
 * @package Drupal\akamai\Form
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
      '#description' => $this->t('Set this field to temporarity disable cache clearing during imports, migrations, or other batch processes.'),
    );

    $form['disable_fieldset']['akamai_disabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Disable cache clearing'),
      '#default_value' => $config->get('akamai_disable'),
    );

    $form['akamai_restapi_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('REST API Endpoint'),
      '#description'   => $this->t('The URL of the Akamai REST API call e.g. "https://api.ccu.akamai.com/ccu/v2/queues/default"'),
      '#default_value' => $config->get('akamai_restapi_endpoint'),
    );

    $form['akamai_basepath'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Base Path'),
      '#default_value' => $config->get('akamai_basepath'),
      '#description' => $this->t('The URL of the base path (fully qualified domain name) of the site.  This will be used as a prefix for all cache clears (Akamai indexs on the full URI). e.g. "http://www.example.com"'),
      '#required' => TRUE,
    );

    $form['akamai_timeout'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Timeout Length'),
      '#description' => $this->t("The timeout used by when sending the cache clear request to Akamai's servers. Most users will not need to change this value."),
      '#size' => 5,
      '#maxlength' => 3,
      '#default_value' => $config->get('akamai_timeout'),
      '#required' => TRUE,
    );

    $form['akamai_username'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cache clearing user'),
      '#default_value' => $config->get('akamai_username'),
      '#description' => $this->t('The user name of the account being used for cache clearing (most likely an email)'),
      '#required' => TRUE,
    );

    if ($config->get('akamai_password')) {
      $password_status_text = t('Akamai CCU Password is set.  Use the fields below to change or leave blank to use the existing password.');
    }
    else {
      $password_status_text = t('Your Akamai CCU Password is not set.  Please set it using the fields below.');
    }

    $form['password_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Akamai CCU Password'),
      '#description' => $password_status_text,
    );

    $form['password_fieldset']['akamai_password'] = array(
      '#type' => 'password_confirm',
      '#title' => $this->t('Cache clearing password'),
      '#description' => $this->t('The password of the cache clearing user'),
    );

    $form['akamai_domain'] = array(
      '#type' => 'select',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('akamai_domain'),
      '#options' => array(
        'staging' => $this->t('Staging'),
        'production' => $this->t('Production'),
      ),
      '#description' => $this->t('The Akamai domain to use for cache clearing'),
      '#required' => TRUE,
    );

    $form['akamai_action'] = array(
      '#type' => 'select',
      '#title' => $this->t('Clearing Action Type Default'),
      '#default_value' => $config->get('akamai_action'),
      '#options' => array(
        'remove' => $this->t('Remove'),
        'invalidate' => $this->t('Invalidate'),
      ),
      '#description' => $this->t('The default clearing action.  The options are <em>remove</em> (which removes the item from the Akamai cache) and <em>invalidate</em> (which leaves the item in the cache, but invalidates it so that the origin will be hit on the next request)'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitform(array &$form, FormStateInterface $form_state) {
    $this->config('akamai.settings')
      ->set('akamai_disabled', $form_state->getValue('akamai_disabled'))
      ->set('akamai_restapi_endpoint', $form_state->getValue('akamai_restapi_endpoint'))
      ->set('akamai_basepath', $form_state->getValue('akamai_basepath'))
      ->set('akamai_timeout', $form_state->getValue('akamai_timeout'))
      ->set('akamai_username', $form_state->getValue('akamai_username'))
      ->set('akamai_password', $form_state->getValue('akamai_password'))
      ->set('akamai_domain', $form_state->getValue('akamai_domain'))
      ->set('akamai_action', $form_state->getValue('akamai_action'))
      ->save();

    drupal_set_message($this->t('Settings saved.'));
  }

}
