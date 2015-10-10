<?php

/**
 * @file
 * Contains Drupal\akamai\Form\AkamaiCacheControlForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A simple form for testing the Akamai integration, or doing manual clears.
 */
class AkamaiCacheControlForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'akamai_cache_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('akamai.settings');
    $form = array();

    $form['paths'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Paths/URLs'),
      '#description' => $this->t('Enter one URL per line. URL entries should be relative to the basepath. (e.g. node/1, content/pretty-title, sites/default/files/some/image.png'),
      '#required' => TRUE,
    );

    $form['domain_override'] = array(
      '#type' => 'select',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('akamai_domain'),
      '#options' => array(
        'staging' => $this->t('Staging'),
        'production' => $this->t('Production'),
      ),
      '#description' => $this->t('The Akamai domain to use for cache clearing.  Defaults to the Domain setting from the settings page.'),
    );

    $form['refresh'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Clearing Action Type'),
      '#default_value' => $config->get('akamai_action'),
      '#options' => array(
        'remove' => $this->t('Remove'),
        'invalidate' => $this->t('Invalidate'),
      ),
      '#description' => $this->t('<b>Remove:</b> Purge the content from Akamai edge server caches. The next time the edge server receives a request for the content, it will retrieve the current version from the origin server. If it cannot retrieve a current version, it will follow instructions in your edge server configuration.<br/><br/><b>Invalidate:</b> Mark the cached content as invalid. The next time the Akamai edge server receives a request for the content, it will send an HTTP conditional get (If-Modified-Since) request to the origin. If the content has changed, the origin server will return a full fresh copy; otherwise, the origin normally will respond that the content has not changed, and Akamai can serve the already-cached content.<br/><br/><b>Note that <em>Remove</em> can increase the load on the origin more than <em>Invalidate</em>.</b> With <em>Invalidate</em>, objects are not removed from cache and full objects are not retrieved from the origin unless they are newer than the cached versions.'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Start Refreshing Content'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $akamai = \Drupal::service('akamai.akamaiservice');
    global $base_url;

    foreach (explode(PHP_EOL, $form_state->getValue('paths')) as $path) {
      $akamai->clearUrl($path);
      // drupal_set_message('Going to clear ' . $base_url . '/' . $path);
    }

    drupal_set_message($this->t('Interact with Akamai API.'));
  }

}
