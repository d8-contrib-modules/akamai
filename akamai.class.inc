<?php
// $Id$
/**
 * @file akamai.class.inc
 *    Akamai is a registered trademark of Akamai Technologies, Inc.
 *    This class is an abstraction around the Akamai Cache Control API.       
 */ 
class AkamaiCacheControl { 

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
      'basepath'  => variable_get("AkamaiCC_basepath", ""),
      'name'      => variable_get("AkamaiCC_name", ""),
      'pwd'       => variable_get("AkamaiCC_pwd", ""),
      'action'    => variable_get("AkamaiCC_action", ""),
      'type'      => "arl",
      'domain'    => variable_get("AkamaiCC_domain", ""),               
      'soap_wsdl' => variable_get("AkamaiCC_soap_wsdl", ""),
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
    
    // create soap object
    try {
      $ccuapi = new SoapClient($soap_wsdl,
        array(
          'trace' => 1,
          'features' => SOAP_USE_XSI_ARRAY_TYPE,
        )
      );
      
      // call web service
      $responce = $ccuapi->purgeRequest($name, $pwd, $network, $opt, $uris);
      $is_success = ($responce->resultCode < 300);
      $message = $responce->resultCode .": ". $responce->resultMsg;
      if (!$is_success) {
        throw new Exception($message);
      }
      foreach ($uris as $uri) {
        watchdog('Akamai', t("Akamai %action of %uri"), 
          array('%uri' => $uri, '%action' => $action), WATCHDOG_NOTICE);
      }
    }
    catch (Exception $e) {
      watchdog('Akamai', t("Error Clearing Akamai Cache: %msg"), array('%msg' => $e->getMessage()));
      throw $e;
    }

    return $message;
  }
}
