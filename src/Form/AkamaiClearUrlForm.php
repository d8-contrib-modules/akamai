<?php

/**
 * @file
 * Contains Drupal/akamai/Form/AkamaiClearUrlForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class AkamaiClearUrlForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'akamai_clear_url_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('akamai.settings');
    $form = array();

    $current_uri = $this->getRequest()->getRequestUri();

    $form['path'] = array(
      '#type'  => 'hidden',
      '#value' => $current_uri,
    );
    $form['message'] = array(
      '#type'  => 'item',
      '#title' => $this->t('Refresh URL'),
      '#markup' => $current_uri,
    );
    $form['submit'] = array(
      '#type'  => 'submit',
      '#value' => $this->t('Refresh Akamai Cache'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message($this->t('Interact with Akamai API.'));
  }

}
