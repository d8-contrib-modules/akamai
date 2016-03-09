<?php

/**
 * @file
 * Contains Drupal\akamai\Form\CacheControlForm.
 */

namespace Drupal\akamai\Form;

use Drupal\akamai\AkamaiClient;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * A simple form for testing the Akamai integration, or doing manual clears.
 */
class CacheControlForm extends FormBase {

  /**
   * The akamai client.
   *
   * @var \Drupal\akamai\AkamaiClient
   */
  protected $akamaiClient;

  /**
   * Constructs a new CacheControlForm.
   *
   * @param \Drupal\akamai\AkamaiClient
   *   The akamai client.
   */
  public function __construct(AkamaiClient $akamaiClient) {
    $this->akamaiClient = $akamaiClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('akamai.edgegridclient')
    );
  }

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

    // Disable the form and show a message if we are not authenticated.
    $form_disabled = FALSE;
    if (\Drupal::state()->get('akamai.valid_credentials') == FALSE) {
      $this->showAuthenticationWarning();
      $form_disabled = TRUE;
    }

    $settings_link = Url::fromRoute('akamai.settings');
    $settings_link = $this->l($settings_link->getInternalPath(), $settings_link);
    $paths_description = $this->t(
      'Enter one URL per line. URL entries should be relative to the basepath
      (e.g. node/1, content/pretty-title, sites/default/files/some/image.png).
      Your basepath for Akamai is set as :basepath. If you would like to change
      it, you can do so at @settings.',
      [
        ':basepath' => $config->get('basepath'),
        '@settings' => $settings_link,
      ]
    );

    $form['paths'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Paths/URLs'),
      '#description' => $paths_description,
      '#required' => TRUE,
      '#default_value' => $form_state->get('paths'),
    );

    $domain_override_default = $form_state->get('domain_override') ?: key(array_filter($config->get('domain')));
    $form['domain_override'] = array(
      '#type' => 'select',
      '#title' => $this->t('Domain'),
      '#options' => array(
        'production' => $this->t('Production'),
        'staging' => $this->t('Staging'),
      ),
      '#default_value' => $domain_override_default,
      '#description' => $this->t('The Akamai domain to use for cache clearing.  Defaults to the Domain setting from the settings page.'),
    );

    $action_default = $form_state->get('action') ?: key(array_filter($config->get('action')));
    $form['action'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Clearing Action Type'),
      '#options' => array(
        'remove' => $this->t('Remove'),
        'invalidate' => $this->t('Invalidate'),
      ),
      '#default_value' => $action_default,
      '#description' => $this->t('<b>Remove:</b> Purge the content from Akamai edge server caches. The next time the edge server receives a request for the content, it will retrieve the current version from the origin server. If it cannot retrieve a current version, it will follow instructions in your edge server configuration.<br/><br/><b>Invalidate:</b> Mark the cached content as invalid. The next time the Akamai edge server receives a request for the content, it will send an HTTP conditional get (If-Modified-Since) request to the origin. If the content has changed, the origin server will return a full fresh copy; otherwise, the origin normally will respond that the content has not changed, and Akamai can serve the already-cached content.<br/><br/><b>Note that <em>Remove</em> can increase the load on the origin more than <em>Invalidate</em>.</b> With <em>Invalidate</em>, objects are not removed from cache and full objects are not retrieved from the origin unless they are newer than the cached versions.'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Start Refreshing Content'),
      '#disabled' => $form_disabled,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $urls_to_clear = array();
    foreach (explode(PHP_EOL, $form_state->getValue('paths')) as $path) {
      $url = Url::fromUserInput('/' . trim($path));
      if ($url->isRouted()) {
        $urls_to_clear[] = trim($path);
      }
      else {
        $invalid_urls[] = trim($path);
      }
    }

    if (empty($urls_to_clear)) {
      $form_state->setErrorByName('paths', $this->t('Please enter atleast one valid path for URL purging'));
    }
    if (!empty($invalid_urls)) {
      $paths = implode(",", $invalid_urls);
      $message = $paths . \Drupal::translation()->formatPlural(count($invalid_urls), ' path is invalid and does not exist', ' paths are invalid and do not exist') . $this->t(' on the site.Please provide valid URLs for purging.');
      drupal_set_message($message, 'warning');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $urls_to_clear = array();
    foreach (explode(PHP_EOL, $form_state->getValue('paths')) as $path) {
      $urls_to_clear[] = trim('/' . $path);
    }
    $action = $form_state->getValue('action');
    $this->akamaiClient->setAction($action);
    $this->akamaiClient->setDomain($form_state->getValue('domain_override'));
    $response = $this->akamaiClient->purgeUrls($urls_to_clear);
    if ($response) {
      drupal_set_message($this->t('Requested :action of the following URLs: :urls', [':action' => $action, ':urls' => implode(', ', $urls_to_clear)]));
    }
    else {
      drupal_set_message($this->t('There was an error clearing the cache. Check logs for further detail.'), 'error');
    }
  }

  /**
   * Shows a message to the user if not authenticated to the Akamai API.
   */
  protected function showAuthenticationWarning() {
    $url = Url::fromRoute('akamai.settings');
    $link_text = $this->t('Update settings now');
    $message = 'You are not authenticated to Akamai CCU v2. Until you authenticate, you will not be able to clear URLs from the Akamai cache. @link';
    $message = $this->t($message, ['@link' => $this->l($link_text, $url)]);
    drupal_set_message($message, 'warning');
  }

}
