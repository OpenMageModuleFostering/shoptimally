<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Provide access to global config and other Shoptimally general consts and settings.
 */
class Shoptimally_Core_Helper_Config extends Mage_Core_Helper_Abstract
{
    // caching if shoptimally is currently enabled
    protected $_isEnabled = false;
    
    // caching the remote config helper
    protected $_remoteConfig;
    
    // caching shoptimally domain
    protected $_shoptimallyDomain;
    
    // current version
    const SHOPTIMALLY_VERSION = "1.1.03";
    
    /**
     * init the config helper.
    */
    public function __construct()
    {
        $this->init();
    }
    
    /**
    * init some config related stuff
    */
    protected function init()
    { 
        // get remote config
        $this->_remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
        
        // calculate enabled status
        $this->_isEnabled = ($this->getGeneralSetting('ShoptimallyEnabled')) &&
                (strlen($this->getApiKey()) > 0) &&
                $this->_remoteConfig->get("enabled");
                
        // init shoptimally domain
        $this->_shoptimallyDomain = $this->_remoteConfig->get('shoptimally_domain');
        if (is_null($this->_shoptimallyDomain) || 
            strlen($this->_shoptimallyDomain) == 0)
        {
            $this->_shoptimallyDomain = "api1.shoptimally.com"; 
        }
    }
    
    /**
    * get current version
    */
    public function getVersion()
    {
        //return (string) Mage::getConfig()->getNode()->modules->Shoptimally_Core->version;
        return self::SHOPTIMALLY_VERSION;
    }
    
    /**
     * Return general setting val by name
     * see system.xml for more info.
     *
     * @param $config_name is the config name relative to 'Shoptimally/GeneralSettings/'
     */
    public function getGeneralSetting($config_name)
    {
        return Mage::getStoreConfig('Shoptimally/GeneralSettings/' . $config_name, Mage::app()->getStore());
    }
     
    /**
     * Return debug-related config.
     *
     * @param $configName is the debug config name.
     * @param $default is value to return if debug config doesn't exist.
     */
    protected function getDebugSetting($configName, $default=false)
    {
    	// get debug settings and if exist return it
        $debugSettings = $this->_remoteConfig->get('debug', array());
        if (array_key_exists($configName, $debugSettings))
        {
        	return $debugSettings[$configName];
        }
        
        // return default value
        return $default;
    }
    
    /**
    * return the config dictionary for a specific feature
    * 
    * @param $featureName - the unique identifier of the feature (for example, "FeaturedItems").
    * @param $default - default value to return if not found.
    */
    public function getFeatureConfig($featureName, $default=array())
    {
        return $this->_remoteConfig->get('feature_' . $featureName, $default);
    }
    
    /**
    * return if logging is enabled
    * @param $level is which level of log to test (0 = fatal, 1=warning, 2 = log, 3 = debug)
    */
    public function isLogEnabled($level=2)
    {
        return $this->getDebugSetting("enable_log") == true &&
        		$this->getDebugSetting("log_level") >= $level;
    }
    
    /**
     * Return if a given feature is enabled
     *
     * @param $featureName - string, feature name / identifier
     * @return bool
     */
    public function isFeatureEnabled($featureName)
    {   
        // first make sure shoptimally is generally enabled
        if (!$this->getIsEnabled())
        {
            return false;
        }
        
        // get user data
        $userData = Mage::helper('shoptimally_core/clientData');
        
        // make sure we have valid user id
        $userId = $userData->getUserId();
        if (empty($userId))
        {
            return false;
        }
        
        // now check from features list from the user cookie
        // this will give us the input from the server + the AB testing
        return in_array($featureName, $userData->getEnabledFeatures());
    }

    /**
     * Return the site api key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->getGeneralSetting('ApiKey');
    }

    /**
     * Return if Shoptimally currently enabled
     *
     * @return boolean
     */
    public function getIsEnabled()
    {
        return $this->_isEnabled;
    }

    /**
     * Return the Shoptimally server url
     *
     * @return string
     */
    public function getServerUrl()
    {   
        return $this->_shoptimallyDomain;
    }
    
    /**
    * Return if the shoptimally js file should be loaded in async mode or not
    * 
    * @return boolean
    */
    public function shouldLoadJsAsync()
    {
        return (!$this->_remoteConfig->get("javascript_synced"));
    }

    /**
     * Return the client-js cdn url, for this specific site (based on configured url & api key)
     *
     * @return string
     */
    public function getJsUrl()
    {
        // get javascript from url and replace the <api-key> tag with our api key
        $url = $this->_remoteConfig->get("javascript_url");
        return str_replace("<api-key>", $this->getApiKey(), $url);
    }
}