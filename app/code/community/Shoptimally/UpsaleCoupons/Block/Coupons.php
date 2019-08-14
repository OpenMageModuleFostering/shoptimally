<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\UpsaleCoupons
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This block inject the upsale coupons into the cart page
 */
class Shoptimally_UpsaleCoupons_Block_Coupons extends Mage_Core_Block_Template
{
	// will hold the coupon main helper, which implements the feature
    protected $_main = null;
    
    // init helpers and get coupon data
    public function __construct()
    {
    	try
    	{
    		$this->_main = Mage::helper('shoptimally_upsalecoupons/main');
    		$this->_main->runFeature();
    	}
    	catch (Exception $e)
    	{
    		Mage::helper('shoptimally_core/log')->warn("Unexpected exception while getting upsale coupon!", $e);
    	}
    }
    
    // return if coupons are enabled (this is used by the phtml)
	public function isEnabled()
	{
		// note: don't use $this->_main->.. as it might not exist when this func is called.
		return Mage::helper('shoptimally_upsalecoupons/main')->isEnabled();
	}
	
	// get the html part to put in the page header
	public function getHeaderHtml()
	{
		return $this->_main->getHeaderHtml();
	}
    
    // get coupon html
    public function getHtml()
    {
    	return $this->_main->getHtml();
    }
}