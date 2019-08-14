<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Analytics
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Feature analytics related events - this is when feature successfully runs, rejected, failed etc.
 */
class Shoptimally_Analytics_Helper_FeatureEvents extends Mage_Core_Helper_Abstract
{
	// analytic utils
	protected $_utils = null;
	
	// possible features to report on
	const FEATURE_FEATURED_ITEMS 	= "FeaturedItems";
	const FEATURE_UPSALE_COUPON 	= "UpsaleCoupons";
	const FEATURE_RELATED_PRODUCTS 	= "RelatedProducts";
	
	// possible statuses we can report
	const STATUS_OK 		= "ok";			// everything went ok.
	const STATUS_REJECTED 	= "rejected";	// client chose to reject the results from server and will not show them.
	const STATUS_TIMEOUT	= "timeout";	// timeout occured.
	const STATUS_ERROR		= "error";		// error occured.

	/**
	 * load the currently existing events in queue
	 */
	public function __construct()
	{
		// load the analytic utils
		$this->_utils = Mage::helper('shoptimally_analytics/utils');
	}
	
	/*
	 * send feature-related analytics.
	 * note: this does not send immediately to server, it write it into a cookie and our client-side javascript
	 * will send it to shoptimally server in an async way.
	 * 
	 * these reports are crutial to keep track on features performance and make sure they are efficient and
	 * do a good job in increasing conversion.
	 * 
	 * @param $featureName - feature name, case sensitive. see FEATURE_XXX for options.
	 * @param $status - feature status, see STATUS_XXX for options.
	 * @extra - optional, any extra data we want to add (dictionary).
	 * */
	public function report($featureName, $status, $extra=array()) {
		
		try
		{
			// set data to send to event
			$data = array(
				"feature_name" => $featureName,	
				"code" => $status,
				"extra" => $extra
			);
			
			// send the event
			$this->_utils->addEvent("feature_analytics", $data);
		}
		catch (Exception $e)
		{
			Mage::helper('shoptimally_core/log')->warn("Failed to send feature analytics!", $e);
		}
	}
}