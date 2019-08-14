<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This observer listen to some events required for Shoptimally init process
 * and internal state.
 */
class Shoptimally_Core_Model_Observer
{
    /**
     * handle event when a block is about to render
     * this function is responsible to trigger the 'BlocksInjecter' helper, so we can
     * inject customized blocks.
    */
    public function onBlockAbstractToHtmlBefore(Varien_Event_Observer $obs)
    {
    	try 
    	{
	        // if shoptimally is disabled, skip
	        if (!Mage::helper('shoptimally_core/config')->getIsEnabled())
	        {
	            return;
	        }
	        
	        // call the block utils event
	        Mage::helper('shoptimally_core/blockUtils')->onBlockRender($obs);
        }
        catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
        }
        
        return $this;
    }
    
    /**
     * called when products list is loaded to get products ids
     * */
    public function onProductsListLoaded(Varien_Event_Observer $observer)
    {
    	try
    	{
	    	if (Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
		    	$collection = $observer->getCollection();
		    	$ids = array();
		    	foreach($collection as $product) {
		    		array_push($ids, $product->getId());
		    	}
		    	Mage::helper('shoptimally_core/pageInfo')->_setProductIdsOnPage($ids);
	    	}
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
    	}
    	
    	return $this;
    }
}