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
      'soap_wsdl' => variable_get("akamai_wsdl", ""),
      'email'     => variable_get("akamai_email", ""),
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
    
    $opt = array(
      "action=$action",
      "domain=$domain",
      "type=$type",
    );
    
    if (!empty($email) && $email != AKAMAI_EMAIL_DISABLE) {
      $opt[] = "email-notification=$email";
    }
    
    try {
      $ccuapi = new SoapClient($soap_wsdl,
        array(
          'trace' => 1,
          'features' => SOAP_USE_XSI_ARRAY_TYPE,
        )
      );

      $response = $ccuapi->purgeRequest($name, $pwd, '', $opt, $uris);
      $is_success = ($response->resultCode < 300);
      if (!$is_success) {
        throw new Exception($response->resultMsg);
      }
      foreach ($uris as $uri) {
        watchdog('Akamai', t("Akamai %action of %uri: %message"), 
          array('%uri' => $uri, '%action' => $action, '%message' => $response->resultMsg), WATCHDOG_NOTICE);
      }
      
      return array(
        'success' => TRUE,
        'code' => $response->resultCode,
        'message' => $response->resultMsg,
      );
    }
    catch (Exception $e) {
      watchdog('Akamai', t("Error Clearing Akamai Cache: %msg"), array('%msg' => $e->getMessage()));
      throw $e;
    }
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
    
    return array(
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
