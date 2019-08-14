<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This cron job update do some basic Shoptimally timely events.
 * Most important functionality is to update the remote-config file.
 */
class Shoptimally_Core_Model_Cron
{   
    /**
    * called every minute to update the remote config file
    */
    public function updateRemoteConfig()
    {   
    	try
    	{
    		// get api key and make sure its defined. if not, skip.
    		$config = Mage::helper('shoptimally_core/config');
    		$apiKey = $config->getApiKey();
    		$enabled = $config->getGeneralSetting('ShoptimallyEnabled');
    		if ($enabled == false || is_null($apiKey) || strlen($apiKey) == 0)
    		{
    			return;
    		}
    		
    		Mage::helper('shoptimally_core/storage')->set("last_cron_run", date("Y-m-d H:i:s"), true);
    		
    		// get remote config from cdn.
        	Mage::helper('shoptimally_core/remoteConfig')->updateFromCdn();
    	}
    	catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception in updating remote config!", $e);
        }
    }
}