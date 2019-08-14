<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\FeaturedItems
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Observer to liten when products are loaded, to inject the featured items into the collection
 * before showing them.
 */
class Shoptimally_FeaturedItems_Model_Observer
{
    /**
     * called after products list is loaded.
     * in here we will run the feature main logic.
    */
    public function onProductsCollectionLoaded(Varien_Event_Observer $observer)
    {
    	try
    	{
	    	if (Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
		        // get the main helper class and run this feature
		        Mage::helper('shoptimally_featureditems/main')->runFeature($observer->getCollection());
	    	}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
    	}
    	
    	return $this;
    }
}