<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Analytics
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Misc analytic-related utils.
 * This just wraps the functionality of sending event to Shoptimally via the user.
 */
class Shoptimally_Analytics_Helper_Utils extends Mage_Core_Helper_Abstract
{	
	// current events queue
	protected $_events = null;
	
	/**
	 * load the currently existing events in queue
	 */
	public function __construct()
	{
		// get events pending to be pushed to shoptimally
		$cookies = Mage::helper('shoptimally_core/cookie');
		$this->_events = $cookies->getCookie("shoptimally_events_queue", true, array());
	}
	
    /**
     * add event for Shoptimally to send.
     * for example, when magento detect add-to-cart event, we will use this
     * function to pass the data to the Shoptimally client js.
     *
     * @param $type - srting, event type
     * @param $data - data to send with the event
     */
    public function addEvent($type, $data)
    {
    	// get source url
    	try
    	{
    		$srcUrl = Mage::helper('shoptimally_core/urlUtils')->getActualCurrentUrl();
    	}
    	catch (Exception $e)
    	{
    		$srcUrl = null;
    	}
    	
        // set data to push
        $to_push = array(
            'type' => $type,
            'data' => $data,
        	'src_url' => $srcUrl
        );

        // for debug
        Mage::helper('shoptimally_core/log')->debug("New event to send " . $type . ": ", $to_push);

        // add new event and re-set the cookie
        array_push($this->_events, $to_push);
        $cookies = Mage::helper('shoptimally_core/cookie');
        $cookies->setCookie("shoptimally_events_queue", $this->_events, true);
    }
}