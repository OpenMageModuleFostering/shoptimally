<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Analytics
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * The observer to listen to different events we want to collect analytics about.
 */
class Shoptimally_Analytics_Model_Observer
{

    /**
     * handle cart save
     * write event to shoptimally
     */
    public function onCartSave(Varien_Event_Observer $obs)
    {
    	try 
    	{
	    	if (!Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
	    		return $this;
	    	}
	    	
	        // get utils
	        $userEvents = Mage::helper('shoptimally_analytics/userEvents');
	
	        // get all cart items
	        $newCartData = $userEvents->getCartItemsConverted();
	        
	        // get previous cart and compare with new cart to send events
	        $prevCart = $userEvents->getCartFromCookie();
	        $userEvents->compareCartsAndSendEvents($prevCart, $newCartData);
	
	        // set cookie with new cart data
	        $userEvents->writeCartToCookie($newCartData);
	        
        }
        catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
        }
        
        return $this;
    }

    /**
     * handle successful checkout
     * write event to shoptimally
     */
    public function onCheckoutComplete(Varien_Event_Observer $obs)
    {
    	try
    	{
	    	if (!Mage::helper('shoptimally_core/config')->getIsEnabled())
	    	{
	    		return $this;
	    	}
	    	
	    	// get utils
	    	$userEvents = Mage::helper('shoptimally_analytics/userEvents');
	    	$objUtils = Mage::helper('shoptimally_core/objectUtils');
	    	
	    	// get current order
	    	$order = new Mage_Sales_Model_Order();
	    	$incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
	    	$order->loadByIncrementId($incrementId);
	    	
	    	// this if is important because if its null it will create fatal that we will not catch.
	    	if (is_null($order))
	    	{
	    		Mage::helper('shoptimally_core/log')->warn("Failed to get order data from observer!");
	    		return $this;	
	    	}
	    	
	    	// get order data
	    	$orderData = $order->getData();
	    	
	    	// get cart and clear our cart cookie.
	    	// its important to clean cart cookie now because after the checkout magento will empty the cart and save, and if we still
	    	// have items in our shoptimally cart cookie we will think there was an item-removed events and send false "remove items".
	    	$currCartData = $userEvents->getCartFromCookie();
	    	$userEvents->writeCartToCookie(array());
	    	
	    	// get all checkout totals (prices, tax, shipping, etc.)
	    	$keys = array("grand_total", "subtotal", "shipping_amount", "tax_amount");
	    	$orderData = $objUtils->array_extract($orderData, $keys, -1);
	    	
	        // add checkout event
	        $data = array(
	        		"cart_items" => $currCartData,
	        		"order_data" => $orderData,
	        );
	        $userEvents->reportCheckout($data);
		}
        catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Unexpected exception in observer!", $e);
        }
        
        return $this;
    }
}