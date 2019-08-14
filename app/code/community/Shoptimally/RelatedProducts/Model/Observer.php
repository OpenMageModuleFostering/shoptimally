<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\RelatedProducts
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Observer to liten when related products are loaded, to replace them with our related products
 * before showing them.
 */
class Shoptimally_RelatedProducts_Model_Observer
{
    /**
     * called after related products list is loaded.
     * in here we will run the feature main logic.
     * */
    public function onProductsCollectionLoaded(Varien_Event_Observer $observer)
    {
    	try 
    	{
	    	if (Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
		        // get the main helper class and run this feature
		        Mage::helper('shoptimally_relatedproducts/main')->runFeature($observer->getCollection());
	    	}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
    	}
    	
    	return $this;
    }
    
    /**
     * called when block is loaded, we check if its a related items block and push items inside.
     * */
    public function onBlockLoaded(Varien_Event_Observer $observer)
    {
    	try 
    	{
	    	if (Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
	    		// get block from event and make sure its the related products block
	    		$block = $observer->getBlock();
	    		if (get_class($block) == "Mage_Catalog_Block_Product_List_Related")
	    		{
	    			// TBD fix empty collection here
	    		}
	    	}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
    	}
    	
    	return $this;
    }
}