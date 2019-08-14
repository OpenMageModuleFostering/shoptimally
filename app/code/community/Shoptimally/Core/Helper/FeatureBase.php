<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * The basic structure for a feature implementation.
 * All Shoptimally features are implemented as helpers (even block-based features - they just wrap a helper),
 * that inherit from this base class.
 * 
 * This helps us reuse feature functionality and have a well-defined feature structure.
 * 
 * What to do when inheriting from this class:
 * 
 * 	1. override 'const NAME' with feature name (case sensitive).
 * 	2. override '_runFeatureImp()' and put main logic inside.
 * 	2. for logging and config use the helpers '$this->_log' and '$this->_config'.
 * 	3. for ajax requests use '$this->sendAjax()'.
 * 	4. don't try-catch stuff, its already handled. you can crash freely.
 * 	5. if you choose to reject server's answer and not show the feature, report it using 'reportRejected()'
 * 	6. if you have any magento error that's not an exception but should be reported, use 'reportError()'.
 * 	7. at the end of the implementation if all goes well, report to server by using 'reportSuccess()'. 
 */
class Shoptimally_Core_Helper_FeatureBase extends Mage_Core_Helper_Abstract
{
	// override this const with the feature name.
	// this must match the feature name as defined on Shoptimally server etc.
	const NAME = "FeatureName";
	
	// will hold the required helpers that are loaded by default for all features
	protected $_analytics = null;
	protected $_config = null;
	protected $_server = null;
	protected $_log = null;
	
	// to make sure a state was reported
	private $_was_reported = false;
	
	/**
	 * init all the required helpers
	 */
	public function __construct()
	{
		// init all helpers
		$this->_analytics = Mage::helper('shoptimally_analytics/featureEvents');
		$this->_config = Mage::helper('shoptimally_core/config');
		$this->_server = Mage::helper('shoptimally_core/server');
		$this->_log = Mage::helper('shoptimally_core/log');
		
		// generate unique feature request id (required for feature analytics)
		try {
			$this->_feature_event_id = $this->_generateFeatureEventId();
		} catch(Exception $e) {
			$this->_feature_event_id = "error";
		}
		
		// get feature-specific configuration
		$this->_featureConfig = $this->_config->getFeatureConfig($this->getName());
	}
	
	/**
	 * generate a random string used as feature event id for analytics.
	 * */
	private function _generateFeatureEventId($length=24) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	
	/**
	 * get feature config (from the 'feature_<FeatureName>' section in the remote config file)
	 * @param name - config name.
	 * @param default - default to return if undefined.
	 * */
	protected function getFeatureConfig($name, $default=null)
	{
		if (isset($this->_featureConfig[$name])) 
		{
			return $this->_featureConfig[$name];
		}
		return $default;
	}
	
	/**
	 * return the name of this feature
	 * */
	public function getName()
	{
		return static::NAME;
	}
	
	/**
	 * send request to Shoptimally server and return response.
	 * - if failed, will report feature error and return null.
	 * - if timedout, will report feature timeout and return null.
	 * 
	 * this function expect to get response in the Shoptimally feature response format, eg a dictionary
	 * with metadata and the actual result in "result".
	 * so this function returns the part that is inside the "result", but if key is not present will just
	 * return the http response (to support old APIs or ajax to urls that are not feature actions).
	 * */
	protected function sendAjax($url, $data, $timeout=1)
	{
		// add feature event id to data
		if (!is_null($data))
		{
			$data["feature_event_id"] = $this->_feature_event_id;
		}
		
		// send the request
		$response = $this->_server->sendRequest($url, $data, $timeout);
		 
		// if exception happened:
		if (is_null($response) || $response->isError()) {
			
			// report about a timeout
			if ($this->_server->getLastErrorMessage() == "Unable to read response, or response is empty")
			{
				$this->reportTimeout();
			}
			// report about other errors
			else
			{
				$this->reportError("Error in ajax! url: '" . $url . "'. Error: " . $this->_server->getLastErrorMessage());
			}

			// return null
			return null;
		}
		
		// no error, time to return response!
		
		// if there's a "result" key in response, return the result (it means its a valid feature response)
		if (array_key_exists("_result", $response))
		{
			return $response["_result"];
		}
		// if no result key, just return the whole response
		else
		{
			return $response;
		}
	}
	
	private function _doActualReport($status, $extraData=null)
	{
		// default extra data
		if (is_null($extraData)) {$extraData = array();}
		
		// add feature event id to extra data
		$extraData['feature_event_id'] = $this->_feature_event_id;
		
		// add event to send
		$analytics = $this->_analytics;
		$this->_analytics->report($this->getName(), $status, $extraData);
		
		// set that was reported successfully
		$this->_was_reported = true;
	}
	
	/**
	 * report failure of this feature (call this on error and exceptions)
	 * @param $msg - fail message.
	 * @param $extraData - any extra data to add to the report.
	 * */
	protected function reportError($msg)
	{
		// report warning to log and shoptimally analytics
		$this->_log->warn("'" . $this->getName() . "' Failed to run. reason: " . $msg);
		$analytics = $this->_analytics;
		$this->_doActualReport($analytics::STATUS_ERROR);
	}

	/**
	 * report failure of this feature due to timeout.
	 * */
	protected function reportTimeout()
	{
		// report warning to log and shoptimally analytics
		$this->_log->warn("'" . $this->getName() . "' got timeout!");
		$analytics = $this->_analytics;
		$this->_doActualReport($analytics::STATUS_TIMEOUT);
	}
	
	/**
	 * report rejected - when we got answer from server and everything was ok, but we chose not to show it
	 * at this time. for example, this happens if we get too few items to show in featured items.
	 * */
	protected function reportRejected()
	{
		// report warning to log and shoptimally analytics
		$analytics = $this->_analytics;
		$this->_doActualReport($analytics::STATUS_REJECTED);
	}
	
	/**
	 * report success - when this feature was successfully displayed and worked.
	 * @param $extraData - any extra data to add to the report.
	 * */
	protected function reportSuccess($extraData=null)
	{
		// report warning to log and shoptimally analytics
		$analytics = $this->_analytics;
		$this->_doActualReport($analytics::STATUS_OK, $extraData);
	}
	
	/**
	 * report success + products replacement. This is to report success and update the original-items and
	 * acutllay-replaced-to items lists.
	 * @param $originalItems - collection of original items.
	 * @param $resultItems - collection of actual result items.
	 * */
	protected function reportSuccessReplacement($originalItems=null, $resultItems=null)
	{
		$data = array();
		if (!is_null($originalItems)) {$data["original_items"] = $originalItems->getAllIds();}
		if (!is_null($resultItems)) {$data["result_items"] = $resultItems->getAllIds();}
		$this->reportSuccess($data);
	}
	
	/**
	 * return if this feature is currently enabled
	 */
	public function isEnabled()
	{
		$config = $this->_config;
		return (($config->isFeatureEnabled($this->getName())) &&
				($config->getIsEnabled()));
	}
	
	/**
	 * this function will be called if feature is disabled.
	 * this might be required for features that are blocks, and we want to put
	 * a default placeholder or an empty block when disabled
	 * */
	protected function _runIfDisabled()
	{
	}
	
	/**
	 * run the feature.
	 * @param $data - any required data for the execution of the feature (optional)
	 * @return true if run with no errors, false if didn't run (disabled) or had an exception or problem.
	 * */
	public function runFeature($data=null)
	{
		try 
		{
			// if enabled, execute feature
			if ($this->isEnabled())
			{
				$this->_runFeatureImp($data);
				return true;
			}
			// if not enabled:
			else
			{
				$this->_runIfDisabled();
				return false;
			}
		}
		catch(Exception $e)
		{
			$this->reportError($e->getMessage());
			return false;
		}
	}
	
	/**
	 * this is the internal feature impelemnt function.
	 * every feature should impelemnt all the main logic in here.
	 * 
	 * Remember - when this function runs everything is already wrapped in try-catch, that also report
	 * to features analytics. so don't try-catch things inside, and remember to use reportError() on problems
	 * that are not exception, and reportRejected() if deciding this feature should not show anything at this time.
	 * 
	 * also, remember to call reportSuccess() at the end!
	 * */
	protected function _runFeatureImp($data)
	{
		$this->_log->warn("'" . $this->getName() . "' main function not implemented!");
	}
}