<?php

namespace Drupal\akamai;

/**
 * The status of an individual Purge request.
 */
class PurgeStatus {

  /**
   * A unix timstamp of the last time this status was checked.
   *
   * @var int
   */
  protected $lastCheckedTime;

  /**
   * A UUID purge id, provided by Akamai.
   *
   * @var string
   */
  protected $purgeId;

  /**
   * A support id, for contacting Akamai.
   *
   * @var string
   */
  protected $supportId;

  /**
   * URls included in this purge request.
   *
   * @var array
   */
  protected $urls;

  /**
   * Description of the current state of the purge.
   *
   * @var string
   */
  protected $description;

  /**
   * Data store in state, mostly what came back from the API + some additions.
   *
   * @var array
   */
  protected $statusRequests;

  /**
   * HTTP code of last response.
   *
   * @var int
   */
  protected $httpCode;

  /**
   * PurgeStatus constructor.
   *
   * @param array $status_requests
   *   A status response as an array, or a list of responses.
   */
  public function __construct($status_requests) {
    $this->statusRequests = $status_requests;

    // Collate all of the URLs from every request.
    $urls = [];
    foreach ($this->statusRequests as $request) {
      if (array_key_exists('urls_queued', $request)) {
        foreach ($request['urls_queued'] as $url) {
          $urls[] = $url;
        }
      }
    }
    $this->urls = $urls;

    $most_recent_request = array_pop($status_requests);
    $this->lastCheckedTime = $most_recent_request['request_made_at'];
    $this->purgeId = $most_recent_request['purgeId'];
    $this->supportId = $most_recent_request['supportId'];
    $this->httpCode = $most_recent_request['httpStatus'];

    // If the latest status has come from an upstream 'purges' call,
    // the status key is different than from a 'purge' call.
    if (array_key_exists('purgeStatus', $most_recent_request)) {
      $this->description = $most_recent_request['purgeStatus'];
    }
    elseif (array_key_exists('detail', $most_recent_request)) {
      $this->description = $most_recent_request['detail'];
    }

  }

  /**
   * Gets the Purge UUID.
   *
   * @return string
   *   The purge UUID.
   */
  public function getPurgeId() {
    return $this->purgeId;
  }

  /**
   * Gets the last time a check was made against this purge.
   *
   * @return int
   *   A unix timestamp.
   */
  public function getLastCheckedTime() {
    return $this->lastCheckedTime;
  }


  /**
   * Gets a list of all URLs that are in this purge request.
   *
   * @return array
   *   A list of URLs included in this purge.
   */
  public function getUrls() {
    return $this->urls;
  }

  /**
   * Gets a support id code for reference with Akamai.
   *
   * @return string
   *   The support id code.
   */
  public function getSupportId() {
    return $this->supportId;
  }

  /**
   * Gets a description of the current purge state.
   *
   * @return string
   *   A description of the current state of the purge.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Gets HTTP code of most recent response.
   *
   * @return int
   *   The HTTP status code.
   */
  public function getHttpCode() {
    return $this->httpCode;
  }

  /**
   * Checks if a purge is complete.
   *
   * @return bool
   *   TRUE if the purge has been completed, FALSE if not.
   */
  public function isComplete() {
    return $this->getDescription() == 'Done';
  }

  /**
   * Gets the most recently made request for this purge.
   *
   * @return array
   *   An associative array with the purge status of one request.
   */
  public function getMostRecentRequest() {
    return array_slice($this->statusRequests, -1)[0];
  }

}
