<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This cron job update Shoptimally server about changed products every X hours.
 * These low rate updates are just to make sure that if some product updates somehow
 * slipped away (for example due to temporary network failure), those products will still be
 * updated, eventually. even if the admin doesn't save them again.
 */
class Shoptimally_CatalogSync_Model_Cron
{   
    /**
    * called every X minutes to update the Shoptimally server with the latest catalog
    */
    public function updateCatalog()
    {   
    	try 
    	{
	    	if (Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
	    		// get which method to use
	    		$method = Mage::helper('shoptimally_core/remoteConfig')->get("catalog_sync_timely_method");
	    		
	    		switch ($method)
	    		{
		    		// the old-school incremental method
		    		case "incremental":
		    			Mage::helper('shoptimally_catalogsync/timeBased')->doTimelyCatalogUpdate();
		    			break;
		    		
		    		// the new random-based catalog sync
		    		case "random":
		    			Mage::helper('shoptimally_catalogsync/timeBased')->doTimelyCatalogUpdateRandom();
		    			break;
		    			
		    		// disabled
		    		case "none":
		    			Mage::helper('shoptimally_core/log')->log("Time-based did not run because catalog sync is currently disabled (method=none).");
		    			break;
		    		
	    			// invalid value
		    		default:
		    			Mage::helper('shoptimally_core/log')->warn("Invalid time-base catalog sync method! value: " . $method);
		    			break;
	    		}
	    	}
    	}
    	catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception while updating items to server!", $e);
    	}
    }
    
    /**
     * called every X minutes to update products html to the Shoptimally server
     * */
    public function updateProductsHtmls()
    {
    	try
    	{
    		if (Mage::helper('shoptimally_core/config')->getIsEnabled() &&
    			Mage::helper('shoptimally_core/remoteConfig')->get("update_items_html"))
    		{
    			Mage::helper('shoptimally_catalogsync/updateItemsHtmls')->sendProductsHtmlsToServer();
    		}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception while updating item htmls to server!", $e);
    	}
    }
    
    /**
     * called every X minutes to update the Shoptimally server with the latest catalog based on interesting list
     */
    public function updateCatalogInterestingList()
    {
    	try
    	{
    		if (Mage::helper('shoptimally_core/config')->getIsEnabled())
    		{
    			Mage::helper('shoptimally_catalogsync/interestingList')->sendInterestingListToServer();
    		}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception while updating items to server from interesting list!", $e);
    	}
    }
}