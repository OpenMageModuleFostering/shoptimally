<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Wrap communicating with Shoptimally server (via ajax requests)
 */
class Shoptimally_Core_Helper_Server extends Mage_Core_Helper_Abstract
{

    /**
     * The site API key
     * @var string
     */
    protected $_apiKey = '';

    /**
     * Shoptimally API url
     * @var string
     */
    protected $_serverUrl = '';

    /**
     * Shoptimally user id
     * @var string
     */
    protected $_userId = '';

    // holds the exception (if happened) we got from last http request.
    public $lastError = null;
    
    // hold the response we got from last http request.
    public $lastResponse = null;
    
    // the method we use when sending messages to server
    const DEFAULT_METHOD = Varien_Http_Client::POST;
    
    /**
     * init the network helper.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init the api key, user id, and shoptimally URL.
     */
    protected function init()
    {
        $config = Mage::helper('shoptimally_core/config');
        $this->_apiKey = $config->getApiKey();
        $serverUrl = $config->getServerUrl();
        $this->_serverUrl = "http://{$serverUrl}/";
        $this->_userId = Mage::helper('shoptimally_core/clientData')->getUserId();
    }

    /**
     * Send ajax request to Shoptimally server, with api key and all the basic data
     * built-in. Use this function to communicate with Shoptimally's web API.
     *
     * @param $url - relative api url to send to (eg "user/events/")
     * @param $data - optional data to send.
     * @param $timeout - optional request timeout in seconds. if null will use default.
     * @return - http response, or null if had unexpected exception.
     * 
     * note! best way to check for errors after calling this function is to do:
     *   if (is_null($response) || $response->isError()) { ... }
     */
    public function sendRequest($url, $data=array(), $timeout=1)
    {       
        // get full url
        // note: $this->_serverUrl should end with trailing slash /
        $fullUrl = "{$this->_serverUrl}{$url}";
                      
        // set api key and user id to message data
        $data['api_key'] = $this->_apiKey;
        $data['user_id'] = $this->_userId;
        
        // send the request
        return $this->http($fullUrl, self::DEFAULT_METHOD, $data, $timeout);
    }
    
    /**
     * Send http request to anywhere.
     *
     * @param $url - full url to send to.
     * @param $method - http method (GET / POST)
     * @param $data - optional data to send (default to null).
     * @param $timeout - request timeout in seconds. if null will use default.
     * @return - http response, or null if had unexpected exception.
     * 
     * To communicate with Shoptimally API don't use this function, use sendRequest() instead.
     * 
     * note! best way to check for errors after calling this function is to do:
     *   if (is_null($response) || $response->isError()) { ... }
     */
    public function http($url, $method, $data=null, $timeout=1)
    {
    	// reset last error and last response
    	$this->lastError = null;
    	$this->lastResponse = null;
    	
        // create request with url and method
        $request = new Varien_Http_Client();
        $request->setUri($url);
        $request->setMethod($method);
        
        // set data (if provided)
        if (!is_null($data))
        {    
            // set content type header
            $request->setHeaders(array('Content-Type' => 'application/json'));
            
            // set post data
            $request->setRawData(Mage::helper('core')->jsonEncode($data), 'application/json');
        }
        
        // set timeout
        $request->setConfig(array('timeout' => $timeout));
               
        // send the request
        // note: if request got a response, doesn't matter the return code we will get a valid response object
        // with code, and no exception. so if we get server 500 for example, no report will occur here and we'll get
        // a return object and not null.
        //
        // if we get a timeout, we will get a Zend_Http_Client_Exception with message "Unable to read response, or response is empty"
        try 
        {
            $response = $request->request($method);
            $this->lastResponse = $response;
        }
        catch (Exception $e) 
        {
        	$this->reportNetworkError($url, $method, $e);
            return null;
        }
        
        // return the response
        return $response;
    }
    
    // return last error message or null if no errors.
    // this checks if there was exception and return its message, and if not, check if we got error code
    // from server and return error code instead. if all well return null.
    // note: this is valid for the lifetime of this instance only.
    public function getLastErrorMessage()
    {
    	// if got exception:
    	if (!is_null($this->lastError))
    	{
    		return $this->lastError->getMessage();
    	}
    	
    	// if got response but its error
    	if (!is_null($this->lastResponse) && $this->lastResponse->isError())
    	{
    		return "Got error code from server - " . $this->lastResponse->getStatus() . ".";
    	}
    	
    	return null;
    }
    
    // report an error
    private function reportNetworkError($url, $method, $error)
    {
    	Mage::helper('shoptimally_core/log')->warn("Exception while sending http request!",
    			array("exception" => $error->getMessage(), "url" => $url, "method" => $method));
    	$this->lastError = $error;
    }
}