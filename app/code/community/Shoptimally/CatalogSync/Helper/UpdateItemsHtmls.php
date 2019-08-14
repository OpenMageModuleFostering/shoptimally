<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This class responsible to send products htmls to Shoptimally server, for ajax-based renderings.
 */
class Shoptimally_CatalogSync_Helper_UpdateItemsHtmls extends Mage_Core_Helper_Abstract
{
	
	/**
	 * Send item htmls to server
	 */
	public function sendProductsHtmlsToServer()
	{
		// get some core utilities
		$log = Mage::helper('shoptimally_core/log');
		$storage = Mage::helper('shoptimally_core/storage');
		$remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
		$interestingListConfig = $remoteConfig->get("catalog_sync_interesting_list");
		
		// get current interesting list
		$productIds = $storage->get("htmls_interesting_list", array(), true);

		// zero the htmls interesting list
		$storage->set("htmls_interesting_list", array(), true);
		
		// slice just the ids we want to send based on interesting products list settings
		$toSendCount = $interestingListConfig["max_products_send_count"];
		$productIds = array_slice($productIds, 0, $toSendCount);
		
		// create a special block to render the products html
		$block = Mage::app()->getLayout()->createBlock('shoptimally_catalogsync/productsRenderer')
						->setTemplate('catalog/product/list.phtml');
		
		// log report
		$log->debug("Update Products Html: Send html of " . count($productIds) . " products from interesting list.");
		
		// prepare data to send - dictionary with product_id => html
		$data = array();
		foreach ($productIds as $id)
		{
			$block->setProductsList(array($id));
			$data[$id] = $block->toHtml();
		}
		
		// send products htmls
		$server = Mage::helper('shoptimally_core/server');
		$response = $server->sendRequest("sites/update_products_html/", array("items" => $data), 30);
		 
		// report errors
		if (is_null($response) || $response->isError()) {
			Mage::helper('shoptimally_core/log')->warn(
					"Failed to update server with products htmls!",
					$response);
		}
	}
}