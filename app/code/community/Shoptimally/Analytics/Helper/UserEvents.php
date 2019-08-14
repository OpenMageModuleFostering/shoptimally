<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Analytics
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * User-events related analytics. This handle things like add-to-cart, remove-from-cart, checkout-complete.
 */
class Shoptimally_Analytics_Helper_UserEvents extends Mage_Core_Helper_Abstract
{
	// all event types
	const EVENT_ADD_TO_CART = "add_to_cart";
	const EVENT_REMOVE_FROM_CART = "remove_from_cart";
	const EVENT_CHECKOUT_COMPLETE = "checkout_complete";
	
	// current events queue
	protected $_utils = null;
	
	/**
	 * load the currently existing events in queue
	 */
	public function __construct()
	{
		// load the analytic utils
		$this->_utils = Mage::helper('shoptimally_analytics/utils');
	}
	
	/**
	 * report checkout event
	 * */
	public function reportCheckout($cart)
	{
		$this->addUserEvent(self::EVENT_CHECKOUT_COMPLETE, $cart);
	}
	
    /**
     * get unique id from quota item
     * @return item id
     */
    public function getIdFromItem($item)
    {
        return $item->getProduct()->getId();
    }

    /**
     * get quantity from quota item
     * @return quantity
     */
    public function getItemQuantity($item)
    {
        return $item->getData()['qty'];
    }
    
    /*
     * return parent product id from quote item
     * */
    private function getItemParentProductId($item)
    {
    	if ($item->getParentItem())
    	{
    		return $item->getParentItem()->getProduct()->getId();
    	}
    	return null;
    }

    /**
     * get all cart items from current cart and convert to our desired format of [{unique_id, quantity}, ]
     * @param $cartItems - all the items in cart you want to process. if null (default) will take from current checkout cart.
     * @return list of items from cart. every item in list is a dictionary with 'unique_id' and 'quantity'.
     */
    public function getCartItemsConverted($cartItems=null)
    {
    	// if not provided, get cart items from cart
    	if (is_null($cartItems))
    	{
        	$cartItems = Mage::getModel('checkout/cart')->getQuote()->getAllItems();
    	}
        
        // convert to our format
        $cartData = array();
        foreach ($cartItems as $item) {
        
        	// get id and quantity
        	$id = $this->getIdFromItem($item);
        	$quantity = $this->getItemQuantity($item);
        	$parentId = $this->getItemParentProductId($item);
        	$parentQuoteId = $item->getParentItemId();
        	
        	// get current item data
        	$newItem = array (
        			'unique_id' => $id,
        			'quantity' => $quantity,
        			'parent_id' => $parentId,
        			'parent_quote_id' => $parentQuoteId,
        			'quote_id' => $item->getId(),
        			'unit_price' => $item->getProduct()->getFinalPrice(),
        	);
        	
        	// iterate over cart data we already have, and if this item already exist add to its quantity.
        	// you might wonder how this might happen? answer is this:
        	// 1. user add item, lets say a baby diaper.
        	// 2. user tries to add a bundle of baby diaper + toy.
        	// 3. however, in diaper + toy the toy is out of stock, so it only adds the diaper.
        	// 4. because its not really bundle, the parent id is null. however, magento still identify
        	//    the two items as different items and store their quantity separately.
        	$wasAddedToExisting = false;
        	foreach ($cartData as $index => $prevItem)
        	{
        		if ($this->isSameCartItem($newItem, $prevItem))
        		{
        			$prevItem["quantity"] += $newItem["quantity"];
        			$cartData[$index] = $prevItem;
        			$wasAddedToExisting = true;
        			break;
        		}
        	}
        
        	// add current data to cart data
        	if (!$wasAddedToExisting)
        	{
        		array_push($cartData, $newItem);
        	}
        }
        
        // return result
        return $cartData;
    }

    /**
     * save current cart to cookie, so the js client will be able to access it and we can get it later.
     * @param $cart - current cart data
     */
    public function writeCartToCookie($cart)
    {
    	// get cookie utils
        $cookies = Mage::helper('shoptimally_core/cookie');
        $cookies->setCookie("shoptimally_curr_cart", $cart, true);
    }

    /**
     * return cart from stored cookie
     */
    public function getCartFromCookie()
    {
    	// get previous cart data
    	$cookies = Mage::helper('shoptimally_core/cookie');
    	return $cookies->getCookie("shoptimally_curr_cart", true, array());
    }
    
    /**
     * This function gets two entries in the cart struct and return true if its the same item.
     * see "getCartItemsConverted()" for more info about cart format
     * */
    private function isSameCartItem($a, $b)
    {
    	return ($a['quote_id'] === $b['quote_id']);
    }

    /**
     * this function gets old cart and new cart, compare them, and send corresponding add_item and
     * remove_item events.
     * */
    public function compareCartsAndSendEvents($prevCart, $newCart)
    {	 
    	
    	// first iterate over previous cart, to send "remove item" events
    	foreach ($prevCart as $prevItem)
    	{
    		// get id and old quantity of the item
    		$currId = $prevItem['unique_id'];
    		$oldQuantity = $prevItem['quantity'];
    		
    		// skip items with parents, because we already operate on the parents themselves
    		if (!is_null($prevItem["parent_id"])) {continue;}
    		 
    		// get new quantity for current item
    		$newQuantity = 0;
    		foreach ($newCart as $newItem)
    		{
    			if ($this->isSameCartItem($prevItem, $newItem))
    			{
    				$newQuantity = $newItem['quantity'];
    				break;
    			}
    		}
    		 
    		// if quantity decreased, send remove item events
    		if ($newQuantity < $oldQuantity)
    		{
    			// first add the data for the item itself
    			$data = $prevItem;
    			$data['quantity'] = $oldQuantity - $newQuantity;
    			
    			// now convert to list and add all children items as well
    			$data = array($data);
    			foreach ($prevCart as $childItem)
    			{
    				if ($childItem["parent_quote_id"] === $prevItem["quote_id"])
    				{
    					array_push($data, $childItem);
    				}
    			}
    			$this->addUserEvent(self::EVENT_REMOVE_FROM_CART, $data);
    		}
    	}
    	 
    	// now iterate over new cart to send "add item" events
    	foreach ($newCart as $newItem)
    	{
    		// get id and new quantity of the item
    		$currId = $newItem['unique_id'];
    		$newQuantity = $newItem['quantity'];
    		
    		// skip items with parents, because we already operate on the parents themselves
    		if (!is_null($newItem["parent_id"])) {continue;}
    		 
    		// get old quantity for current item
    		$oldQuantity = 0;
    		foreach ($prevCart as $prevItem)
    		{
    			if ($this->isSameCartItem($prevItem, $newItem))
    			{
    				$oldQuantity = $prevItem['quantity'];
    				break;
    			}
    		}
    
    		// if quantity decreased, send remove item events
    		if ($newQuantity > $oldQuantity)
    		{
    			// first add the data for the item itself
    			$data = $newItem;
    			$data['quantity'] = $newQuantity - $oldQuantity;
    			
    			// now convert to list and add all children items as well
    			$data = array($data);
    			foreach ($newCart as $childItem)
    			{
    				if ($childItem["parent_quote_id"] === $newItem["quote_id"])
    				{
    					array_push($data, $childItem);
    				}
    			}
    			$this->addUserEvent(self::EVENT_ADD_TO_CART, $data);
    		}
    	}
    }
    
    /**
     * add event for Shoptimally to send.
     * for example, when magento detect add-to-cart event, we will use this
     * function to pass the data to the Shoptimally client js.
     *
     * @param $type - srting, event type
     * @param $data - data to send with the event
     */
    private function addUserEvent($type, $data)
    {
    	$this->_utils->addEvent($type, $data);
    }
}