<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\UpsaleCoupons
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This helper provide the main functionality of the 'Upsale Coupons' feature.
 */
class Shoptimally_UpsaleCoupons_Helper_Main extends Shoptimally_Core_Helper_FeatureBase
{
	// define feature name (see Shoptimally_Core_Helper_FeatureBase for more info)
	const NAME = "UpsaleCoupons";
	
	// will hold coupon data after feature execution.
	private $_couponData = null;
	
	// will hold the final coupon html after finish execution
	protected $_html = "";
	protected $_headerHtml = "";
	
	/**
	 * if disabled set false coupon
	 * */
	protected function _runIfDisabled()
	{
		$this->_couponData = array("have_coupon" => false);
	}
	
	/**
	 * push the Related Products instead of the original related products collection.
	 * this should be called from the observer, after the related products list was loaded.
	 *
	 * @param $relatedProductsCollection - the related products list we want to replace.
	 */
	protected function _runFeatureImp($relatedProductsCollection)
	{
		// set default disabled coupon
		$this->_runIfDisabled();

		// get some required helpers
		$prodUtils = Mage::helper('shoptimally_core/productsUtils');
		$cookies = Mage::helper('shoptimally_core/cookie');
		$remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
		
		// check if there's a previous coupon cookie
		$lastCoupon = $cookies->getCookie("shoptimally_last_coupon", true);
		
		// get all product ids in cart
		$cartProducts = array();
		$cartItems = $prodUtils->getCartItems();
		foreach( $cartItems as $item )
		{
			$data = array(
					"id" => $item->getProductId(),
					"amount" => $item->getQty(),
					"tax" => $item->getTaxAmount(),
			);
			array_push($cartProducts, $data);
		}
		
		// get total price
		$grandTotal = $prodUtils->getCartTotal();
		
		// prepare data to send to get_coupons
		$data = array(
				"total_price" => $grandTotal,
				"cart_items" => $cartProducts,
				"previous_coupon" => $lastCoupon,
		);
		
		// send request to server
		$response = $this->sendAjax("features/upsale_coupons/get", $data, 2);
		 
		// if exception or error skip
        if (is_null($response) || $response->isError()) {
            return null;
        }
		
		// parse coupon data from response body
		$this->_couponData = Mage::helper('core')->jsonDecode($response->getBody());
		
		// if coupon enabled get the html template we wan't to use from the response
		if ($this->_couponData["have_coupon"])
		{
			// get feature config
			$couponSettings = $remoteConfig->get("feature_UpsaleCoupons");
			 
			// get html code for this coupon
			$this->_html = $couponSettings[$this->_couponData["coupon_template"]];
			$this->_headerHtml = $couponSettings["header_html"];
		}
		// if don't have coupon report rejected
		else
		{
			$this->reportRejected();
		}
		
		// store coupon data in cookie
		$cookies->setCookie("shoptimally_last_coupon", $this->_couponData, true);
	}
	
	// get coupon html, with all coupon data injected into html snippet
	public function getHtml()
	{
		try
		{
			// if don't have a valid coupon, return empty html
			if (!$this->_couponData["have_coupon"])
			{
				return "";
			}
			
			// get html and replace template parts
			$html = $this->_html;
			 
			// do all textual replacements based on coupon data
			foreach ($this->_couponData["replacements"] as $key => $value)
			{
				$html = str_replace($key, $value, $html);
			}
			 
			// report success and return the html
			$this->reportSuccess();
			return $html;
		}
		catch(Exception $e)
		{
			$this->reportError("Exception while converting to html: " . $e->getMessage());
		}
	}
	
	// return the coupon header html
	public function getHeaderHtml()
	{
		return $this->_headerHtml;	
	}
}