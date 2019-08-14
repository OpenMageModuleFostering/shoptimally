<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Provide URL manipulations and utilities.
 */
class Shoptimally_Core_Helper_UrlUtils extends Mage_Core_Helper_Abstract
{
    /**
     * convert absolute url to relative url
     * @param $url is absolute url to convert
     * @return relative url, without domain
     */
    public function toRelative($url)
    {
        $url = Mage::getSingleton('core/url')->parseUrl($url);
        return $url->getPath();
    }

    /**
     * get *relative* current url
     */
    public function getCurrentUrl()
    {
        return $this->toRelative(Mage::helper('core/url')->getCurrentUrl());
    }

    /**
     * get *relative* previous url
     * note: assuming previous url was inside our domain
     */
    public function getPreviousUrl()
    {
        return $this->toRelative(Mage::getSingleton('core/session')->getLastUrl());
    }

    /**
     * get url and return if its a visible url, eg an actual page users can browse.
     * note: some urls are just magento internal tricks or transitions, these pages are not visible.
     * for example, the cart page is visible. however, when you do checkout, it switch to something like:
     * "/cart/checkout/processid?=3852908593032...." which immediately switch forward to next page.
     * that middle-url during the checkout process is not a visible page.
     */
    protected function isVisibleUrl($url)
    {
    	// make a list of invalid urls
    	$invalidUrls = array(
    			'/checkout/cart/add/',
    			'/checkout/cart/remove/',
    			'/checkout/cart/updatePost/',
    			'/checkout/cart/index/',
    			'/cart/checkout/processid?='
    	);
    	
    	// check if url is inalid
    	foreach($invalidUrls as $badUrl)
    	{
    		if (strpos($url, $badUrl) !== false) {
    			return false;
    		}
    	}
    	
        // if got here means its a valid, visible page
        return true;
    }

    /**
     * get meaningful current relative url.
     * what this means? when you add item to cart, for example, magento have some middle url
     * to active the event. something like: "cart/add-item?id=53989038..." etc.
     * sometimes we want to get the REAL url we got from, and not the internal url.
     * this function helps us get it. will either return current url, or if its an internal magento
     * trick will return previous url instead.
     */
    public function getActualCurrentUrl()
    {
        // first try to get current url
        $ret = $this->getCurrentUrl();
        
        // if its an invisible url fix it
        try
        {
	        // if its not magento built-in url, return it
	        if ($this->isVisibleUrl($ret))
	        {
	            return $ret;
	        }
	        
	        // if its one of the checkout pages, its a special case.
	        // there are lots of special pages there, sometimes event more then one hop.
	        // so if url has "/checkout/cart/" just remove everything after the cart.
	        if (strpos($ret, "/checkout/cart/") !== false) {
	        	$ret = explode("/cart/", $ret);
	        	return $ret[0] . '/cart/';
	        }
	        
	        // if got here it means curr url is magento built-in, so we return last url instead
	        return $this->getPreviousUrl();
        }
        catch (Exception $e)
        {
        	Mage::helper('shoptimally_core/log')->warn("Error while trying to get actual URL.", $e);
        	return $ret;
        }
    }

}