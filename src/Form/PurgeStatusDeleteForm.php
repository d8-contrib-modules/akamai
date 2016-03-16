<?php
/**
 * @file
 * Contains \Drupal\akamai\Form\PurgeStatusDeleteForm.
 */

namespace Drupal\akamai\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\akamai\StatusStorage;


/**
 * Provides a form for deleting a purge status item.
 */
class PurgeStatusDeleteForm extends ConfirmFormBase {

  /**
   * ID of the purge to delete.
   *
   * @var string
   */
  protected $purgeId;

  /**
   * Purge Status storage service.
   *
   * @var \Drupal\akamai\StatusStorage
   */
  protected $statusStorage;

  /**
   * Constructs a new PurgeStatusDeleteForm.
   *
   * @param \Drupal\akamai\StatusStorage $status_log
   *   Status storage service.
   */
  public function __construct(StatusStorage $status_log) {
    $this->statusStorage = $status_log;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('akamai.status_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'akamai_purge_status_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete purge status %purge_id?', ['%purge_id' => $this->getPurgeId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // @todo: Implement getCancelUrl() method.
    // Go back to the purge detail page.
    return new Url('akamai.statuslog_purge_check', ['purge_id' => $this->getPurgeId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $purge_id = NULL) {
    $this->purgeId = $purge_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->statusStorage->delete($this->purgeId);
    drupal_set_message($this->t('%purge_id deleted.', ['%purge_id' => $this->purge_id]));
    // Redirect to the listing page.
    $form_state->setRedirect('akamai.statuslog_list');
  }

}
