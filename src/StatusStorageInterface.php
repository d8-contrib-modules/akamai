<?php

namespace Drupal\akamai;

interface StatusStorageInterface {

  /**
   * Loads a purge status.
   *
   * @param int $id
   *   The ID of the purge to load.
   *
   * @return array
   *   An array representing the purge, or FALSE if no batch was found.
   */
  public function get($id);

  /**
   * Creates and saves a batch.
   *
   * @param array $batch
   *   The array representing the purge status to create.
   */
  public function save(array $batch);

  /**
   * Updates a batch.
   *
   * @param array $batch
   *   The array representing the purge status to update.
   */
  public function update(array $batch);

  /**
   * Deletes a purge status.
   *
   * @param int $id
   *   The ID of the purge to delete.
   */
  public function delete($id);

  /**
   * Cleans up finished or old statuses.
   */
  //public function cleanup();
}
