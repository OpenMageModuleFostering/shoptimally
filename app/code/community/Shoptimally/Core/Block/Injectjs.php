<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This block inject the shoptimally client js tag into header.
 */
class Shoptimally_Core_Block_Injectjs extends Mage_Core_Block_Template
{
    // return if shoptimally is currently enabled on this site
    public function isEnabled()
    {
        return Mage::helper('shoptimally_core/config')->getIsEnabled();
    }
    
    // get shoptimally version
    public function getVersion()
    {
    	return Mage::helper('shoptimally_core/config')->getVersion();    	
    }

    // get the full url to the shoptimally js for this site
    public function getShoptimallyJsUrl()
    {
    	try
    	{
        	return Mage::helper('shoptimally_core/config')->getJsUrl();
    	}
    	catch (Exception $e)
    	{
    		return "error";
    	}
    }
    
    // get api key
    public function getApiKey()
    {
    	try
    	{
        	return Mage::helper('shoptimally_core/config')->getApiKey();
    	}
    	catch (Exception $e)
    	{
    		return "error";
    	}
    }
    
    // get either "async" or empty string, to enable/disable async js mode
    public function getShoptimallyAsyncMode()
    {
    	try
    	{
	        if (Mage::helper('shoptimally_core/config')->shouldLoadJsAsync()) {
	            return "async";
	        }
	        return "";
    	}
    	catch (Exception $e)
    	{
    		return "async data-sh-error=''";
    	}
    }
    
    // return some extra hinters and metadata we provide for Shoptimally javascript code
    // reutnr json object
    public function getPageInfo()
    {
    	try
    	{
    		// get basic info + host prefix
	    	$ret = Mage::helper('shoptimally_core/pageInfo')->getBasicInfo();
	    	$ret["host_prefix"] = Mage::helper('shoptimally_core/remoteConfig')->get("host_urls_prefix");
	    	
	    	// stringify and return result
	    	return Mage::helper('core')->jsonEncode($ret);
    	}
    	catch (Exception $e)
    	{
    		return "null";
    	}
    }
    
    // get product ids on current page (or empty list if none or failed)
    public function getProductIds()
    {
    	try
    	{
    		$ret = Mage::helper('shoptimally_core/pageInfo')->getProductIds();
    		return Mage::helper('core')->jsonEncode($ret);
    	}
    	catch (Exception $e)
    	{
    		return "[]";
    	}
    }

    // get the shoptimally server url
    public function getServerUrl()
    {
    	try 
    	{
        	return "//" . Mage::helper('shoptimally_core/config')->getServerUrl();
    	}
        catch (Exception $e)
        {
        	return "error";
        }
    }
}