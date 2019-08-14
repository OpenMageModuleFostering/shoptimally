<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Remote config is a remote config file that updates automatically once every X minutes, and its goal
 * is to let Shoptimally control some of the settings remotely, without bothering the shop owner.
 */
class Shoptimally_Core_Helper_RemoteConfig extends Mage_Core_Helper_Abstract
{   
    // will hold global and local configs once loaded
    protected $_config = array();
    
    // Shoptimally cdn domain
    //const CDN_DOMAIN = "cdn.shoptimally.com";
    const CDN_DOMAIN = "s3-eu-west-1.amazonaws.com/shoptimally-ire";
    
    /**
    * load config from storage
    */
    public function __construct()
    { 
        // first set empty config
        $this->_config = array();
        
        // now try to load config from storage
        $this->loadConfig("local");
        $this->loadConfig("global");
        
        // in case a config was not loaded
        if (is_null($this->_config["local"])) {$this->_config["local"] = array();}
        if (is_null($this->_config["global"])) {$this->_config["global"] = array();}
    }
    
    /*
     * for debug purposes, get all config dict
     * */
    public function _getAll()
    {
    	return $this->_config;
    }
    
    /**
    * get config value.
    * @param $key - the config key.
    * @param $default - returned if value does not exist
    */
    public function get($key, $default=null)
    {
        // if local config exist for this key, return it
        if (array_key_exists ($key, $this->_config['local']))
        {
            return $this->_config['local'][$key];
        }
        
        // else, return from the global config
        if (array_key_exists ($key, $this->_config['global']))
        {
            return $this->_config['global'][$key];
        }
        
        // value doesn't exist in global OR local? return $default
        return $default;
    }
    
    /**
    * attempt to load config from local storage
    */
    protected function loadConfig($configType)
    {
        // get storage helper
        $storage = Mage::helper('shoptimally_core/storage');
                
        // get from storage and try to parse and set
        try
        {
            $newConfig = $storage->get($configType . "_config", "[]");
            $this->_config[$configType] = Mage::helper('core')->jsonDecode($newConfig);
        }
        catch (Exception $e) 
        {
            Mage::log("Shoptimally: Invalid format in '" . $configType . "' config file from storage!", $newConfig);
        }
    }
    
    /**
    * Update from the remote config file from the cdn.
    * This is called every X minutes by the cronejob.
    * @param $callback - optional function to call when done / fail.
    * 						function get ($type, $succeed, $reason) as params:
    * 										$type - local / global (it will be called twice).
    * 										$succeed - was this config type loaded successfully (bool).
    * 										$reason - if failed, reason.
    */
    public function updateFromCdn($callback=null)
    {
        // get global config
        $this->fetchConfigFile("global", $callback);
        
        // get local config
        $this->fetchConfigFile("local", $callback);
    }
    
    /**
    * fetch and prase config file.
    * Note: if failed to get config from CDN we just continue with last config.
    * maybe in the future we would like to implement some mechanism that after X fails we turn oursevels disabled,
    * but currently we don't need it.
    * @param $configType - either "global" or "local".
    * @param $callback - optional function to call when done / fail. see updateFromCdn() docs for more info.
    */
    protected function fetchConfigFile($configType, $callback=null)
    {
        // get required helpers
        $log = Mage::helper('shoptimally_core/log');
        $storage = Mage::helper('shoptimally_core/storage');
        $server = Mage::helper('shoptimally_core/server');
        $cdn = self::CDN_DOMAIN;
        
        // to remove annoying "if (!is_null($callback)) {...}" all over the place
        if (is_null($callback))
        {
        	$callback = function($type, $succeed, $errMsg) {};
        }
        
        try {
	 
	        // get url based on type of file
	        switch ($configType)
	        {
	            case "global":
	                $url = "http://{$cdn}/global_config.txt";
	                break;
	                
	            case "local":
	                $key = Mage::helper('shoptimally_core/config')->getApiKey();
	                $key = str_replace('-', "", $key);
	                $url = "http://{$cdn}/sites/{$key}/config.txt"; 
	                break;
	        }
	        
	        // fetch the file
	        $response = $server->http($url, "GET", null, 15);
	
	        // make sure no errors occured
	        if (is_null($response) || $response->isError())
	        {
	            $log->warn("Failed to get '" . $configType . "' config file!");
	            $callback($configType, false, $server->getLastErrorMessage());
	            return;
	        }
	        
	        // parse and set the config file
	        try
	        {
	            $newConfig = $response->getBody();
	            $this->_config[$configType] = Mage::helper('core')->jsonDecode($newConfig);
	        }
	        catch (Exception $e)
	        {
	            $log->warn("Invalid format in '" . $configType . "' config file!", $newConfig);
	            $callback($configType, false, "Invalid format / corrupted file!");
	            return;
	        }
	        
	        try
	        {
	        	// set in persistent storage
	        	$storage->set($configType . "_config", $newConfig);
		        
		        // add timestamp
		        $storage->set($configType . "_config_last_update", date("Y-m-d H:i:s"));
	        }
	        catch (Exception $e)
	        {
	        	$log->warn("Failed to set remote config in storage!", $e);
	        	$callback($configType, false, "Failed to write config to storage! Error: " . $e->getMessage());
	        	return;
	        }
	        
	        // success
	        $callback($configType, true, "");

        } 
        catch (Exception $e) 
        {
        	$log->warn("Unexpected exception while fetching config file!", $e);
        	$callback($configType, false, "Unexpected exception: " . $e->getMessage());
        }
    }
}