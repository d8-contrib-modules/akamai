<?php
/**
 * @file akamai.class.inc
 *    Akamai is a registered trademark of Akamai Technologies, Inc.
 *    This class is an abstraction around the Akamai Cache Control API.
 */

interface AkamaiCacheControl {
  /**
   * Clears the provided URLs from the Akamai Content Cache.
   *
   * @param $paths
   *    A path (or array of paths) to clear from Akamai
   * @return
   *    An array with 2 keys:
   *       success: TRUE or FALSE indicating cache clearing success
   *       message: Blank if successful, the error message if not successful.
   */
  function clear_url($paths);
}

/**
 * Default implementation of the AkamaiCacheControl interface
 */
class AkamaiCacheControlClient implements AkamaiCacheControl {

  private $defaults; //akamai_get_defaults();

  public $parameters;

  /**
   * Constructs an instance of the Akamai Cache Control facade.
   *
   * Valid parameters are specified in the options array as key/value pairs with the
   * parameter name being the key and the parameter setting being the value
   *
   * @param options  An array of parameter options for the Akamae Cache Control
   *                  Web Service. These will override the defaults.
   */
  function __construct($options = array()) {
    $this->defaults =  array(
      'basepath'  => variable_get("akamai_basepath", ""),
      'name'      => variable_get("akamai_username", ""),
      'pwd'       => variable_get("akamai_password", ""),
      'action'    => variable_get("akamai_action", ""),
      'type'      => "arl",
      'domain'    => variable_get("akamai_domain", ""),
      'restapi'   => variable_get("akamai_restapi", ""),
    );

    $this->parameters = array_merge($this->defaults, $options);
  }

  /**
   * Clears the provided URLs from the Akamai Content Cache.
   *
   * @param $paths
   *    A path (or array of paths) to clear from Akamai
   * @return
   *    An array with 2 keys:
   *       success: TRUE or FALSE indicating cache clearing success
   *       message: Blank if successful, the error message if not successful.
   */
  function clear_url($paths) {

    // Grab params
    extract($this->parameters);

    // make paths an array
    if (!is_array($paths)) {
      $url = array($paths);
    }

    // prepend base path to paths to make URIs
    $uris = array();
    foreach ($paths as $path) {
      $path= rtrim(preg_match("/^\//", $path) ? $path : "/$path");
      array_push($uris, $basepath . $path);
    }

    $data = array("type" => "arl", "action" => "$action", "domain" => "$domain", "objects" => $uris);
    $data_string = json_encode($data);
    $data_string = str_replace("\\/", '/', $data_string);

    //url needs to be in the format of scheme://user:pass@url
    $url_parsed = parse_url($restapi);
    $url = $url_parsed['scheme'] . '://' . $name . ':' . $pwd . '@' . $url_parsed['host'] . $url_parsed['path'];
    $response = drupal_http_request($url, array(
        'method' => 'POST',
        'data' => $data_string,
        'timeout' => 5,
        'headers' => array(
           'Content-Type' => 'application/json',
           'Content-Length' => strlen($data_string),
        ),
      )
    );
    $is_success = ($response->code < 300);
    $response_data = json_decode($response->data);
    if (!$is_success) {
      watchdog('Akamai', "Akamai RestAPI failed  %action for %uris with code %code and response: %response", array('%uris' => implode(' ', $uris), '%action' => $action, '%code' => $response->code, '%response' => print_r($response_data, 1)), WATCHDOG_WARNING);
      throw new Exception($response->data);
    }

    watchdog('Akamai', "Akamai RestAPI %action for %uris: Response: %response", array('%uris' => implode(' ', $uris), '%action' => $action, '%response' => $response_data->detail), WATCHDOG_NOTICE);

    return $response;
  }
}

/**
 * Implementation of the AkamaiCacheControl interface that records what was sent to it.
 * Hint: This is only useful for testing.
 */
class RecordingCacheControlClient implements AkamaiCacheControl {

  public $paths;
  public $options;

  /**
   * Constructs an instance of the Akamai Cache Control facade.
   */
  function __construct($options = array()) {
    $this->options = $options;
  }

  /**
   * Records the paths passed in.
   */
  function clear_url($paths) {
    if (!is_array($paths)) {
      $paths = array($paths);
    }
    $this->paths = $paths;

    return (object) array(
      'success' => TRUE,
      'code' => '',
      'message' => '',
    );
  }
}

/**
 * Implementation of the AkamaiCacheControl interface that throws Exceptions.
 * Hint: This is only useful for testing.
 */
class ErrorCacheControlClient implements AkamaiCacheControl {

  /**
   * Constructs an instance of the Akamai Cache Control facade.
   */
  function __construct($options = array()) {
  }

  /**
   * Records the paths passed in.
   */
  function clear_url($paths) {
    throw new Exception(t('Akamai Failure Condition Test'));
  }
}
