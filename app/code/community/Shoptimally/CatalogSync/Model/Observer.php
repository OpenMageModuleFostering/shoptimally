<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This observer listen to events like products save and attribute updates, to tell
 * Shoptimally about that and keep us in sync.
 */
class Shoptimally_CatalogSync_Model_Observer
{
	
	/**
	 * called when products list is loaded to update the "interesting products" list we want to update.
	 * */
	public function onProductsListLoaded(Varien_Event_Observer $observer)
	{
		try
		{
			// make sure shoptimally is enabled
			if (Mage::helper('shoptimally_core/config')->getIsEnabled())
			{	
				// get remote config
				$remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
				
				// make sure this feature is enabled
				$interestingListConfig = $remoteConfig->get("catalog_sync_interesting_list");
				if ($interestingListConfig["enable"])
				{
					// randomly choose if we are going to add to interesting list at this point or not
					$chance = $interestingListConfig["frequency"];
					$roll = rand(0, 100);
					
					// if we should run at this time:
					if ($chance >= $roll)
					{
						$collection = $observer->getCollection();
						Mage::helper('shoptimally_catalogsync/interestingList')->addProductsToInterestingList($collection);
					}
				}
			}
		}
		catch (Exception $e)
		{
			Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
		}
		 
		return $this;
	}
	
    /**
    * called whenever a product is updated
    */
    public function onProductUpdate(Varien_Event_Observer $obs)
    {     
    	try
    	{
	    	if (!Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
	    		return $this;
	    	}
	    	
	        // get updated product
	        $product =  $obs->getProduct();
	        
	        // to make sure we won't mess things up for older versions
	        if (is_null($product))
	        {
	            $log = Mage::helper('shoptimally_core/log');
	            $log->warn("Product was saved but could not send update because failed to get the product from observer!");
	            return $this;
	        }
	        
	        // get log helper
	        $log = Mage::helper('shoptimally_core/log');
	        $log->debug("Product '" . $product->getName() . "' was updated.");
	            
	        // do the update
	        $productCollection = array($product);
	        $productsCount = Mage::helper('shoptimally_catalogsync/utils')->sendUpdateToServer(null, $productCollection);
	    }
        catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
        }
        return $this;
    }
}