<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright � 2015 Shoptimally, Inc.
 *
 * All the functionality to send catalog items based on interesting list / product views
 */
class Shoptimally_CatalogSync_Helper_InterestingList extends Mage_Core_Helper_Abstract
{	
	
	/**
	 * add list of interesting products we want to give priority to next time we send
	 * catalog sync.
	 * @param $products - list of products that were viewed.
	 * 
	 * */
	public function addProductsToInterestingList($products)
	{
		// get interesting list config
		$remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
		$interestingListConfig = $remoteConfig->get("catalog_sync_interesting_list");
		
		// get storage helper
		$storage = Mage::helper('shoptimally_core/storage');
		
		// get sent history, current interesting list and calc how many room we got left
		try {
			$sentHistory = $storage->get("interesting_list_sent_history", array(), true);
			$currList = $storage->get("current_interesting_list", array(), true);
		}
		// on exception zero the lists
		catch (Exception $e) {
			$log->warn("Had exception while updating interesting list, deleted both lists.", $e);
			$currList = array();
			$sentHistory = array();
		}
		
		$roomLeft = $interestingListConfig["max_products_count"] - count($currList);
		
		// if list is full skip this whole function
		if ($roomLeft <= 0)
		{
			return;
		}
		
		// just in case..
		if (is_null($currList)) {$currList = array();}
		if (is_null($sentHistory)) {$sentHistory = array();}
		
		// get ttl, eg how old a product must be to push it into the interesting list
		$ttl = $interestingListConfig["products_ttl"];
		$currTime = time ();
		
		// iterate over the products collection and create a list with only the ids that were not
		// update in the last X hours (configurable)
		$productsToPush = array();
		foreach ($products as $product)
		{
			// if no more room in interesting list, skip
			if ($roomLeft <= 0) 
			{
				break;
			}
			
			// get id
			$id = $product->getId();
			
			// if already appear in the interesting list, skip
			if (in_array($id, $currList)) 
			{
				continue;
			}
			
			// if was recently sent, skip
			if (array_key_exists($id, $sentHistory))
			{
				continue;
			}
			
			// if got here all conditions are met and we add this item to the interesting list!
			array_push($currList, $id);
			$roomLeft--;
		}
		
		// write the updated list to storage
		$storage->set("current_interesting_list", $currList, true);
	}
	

	/**
	 * Called every X minutes to update the Shoptimally server with the latest catalog.
	 * This function uses the "interesting products" list generated by most viewed products.
	 */
	public function sendInterestingListToServer()
	{
		// get some core utilities
		$log = Mage::helper('shoptimally_core/log');
		$storage = Mage::helper('shoptimally_core/storage');
		$remoteConfig = Mage::helper('shoptimally_core/remoteConfig');
		$productUtils = Mage::helper('shoptimally_core/productsUtils');
		
		// get interesting list config
		$interestingListConfig = $remoteConfig->get("catalog_sync_interesting_list");
		
		// if disabled skip
		if (!$interestingListConfig["enable"])
		{
			return;
		}

		// get current iteration
		$currIteration = $storage->get("interesting_list_iteration", 0, true);
		
		// get history list and curr list
		try {
			$sentHistory = $storage->get("interesting_list_sent_history", array(), true);
			$currList = $storage->get("current_interesting_list", array(), true);
		}
		// on exception zero the lists
		catch (Exception $e) {
			$log->warn("Had exception while reading interesting list, deleted both lists.", $e);
			$storage->set("interesting_list_sent_history", array(), true);
			$storage->set("current_interesting_list", array(), true);
		}
		
		$toSendCount = $interestingListConfig["max_products_send_count"];
		
		// if emtpy skip
		if (empty($currList))
		{
			$log->log("Update Catalog from interesting list: No interesting products to update..");
			$idsToSend = array();
		}
		else
		{
			// get how many items to send
			$idsToSend = array_slice($currList, 0, $toSendCount);
			
			// set the htmls update queue
			$productIds = $storage->set("htmls_interesting_list", $idsToSend, true);
		
			// log report
			$log->log("Update Catalog from interesting list (iteration " . $currIteration . "): ", $idsToSend);
			
			// iterate over the interesting list
			$collection = Mage::getModel('catalog/product')->getCollection()
										->addAttributeToFilter('entity_id', array('in' => $idsToSend))
										->addAttributeToSelect('*')
										->load();
			
			// do the update
			$utils = Mage::helper('shoptimally_catalogsync/utils');
			$updatedCount = $utils->sendUpdateToServer(null, $collection);
			
			$log->log("Done, updated " . $updatedCount . " products!");
		}
		
		// get ttl and current time
		$ttl = $interestingListConfig["products_ttl"];
		
		// iterate over history list and remove items that are too old
		$removedCount = 0;
		foreach ($sentHistory as $id => $updateTime)
		{
			// if too old remove
			if ($updateTime > $ttl || $updateTime === $currIteration)
			{
				$removedCount++;
				unset($sentHistory[$id]);
			}
		}
		if ($removedCount > 0)
		{
			$log->debug("Removed " . $removedCount . " products from history list because they were too old.");
		}
		
		// add new items to history list
		if (!empty($idsToSend))
		{
			$log->debug("Adding " . count($idsToSend) . " items to history list.");
			foreach ($idsToSend as $id)
			{
				$sentHistory[$id] = $currIteration;
			}
		}
		
		// increase iteration and save
		if (++$currIteration > $ttl) {$currIteration = 0;}
		$storage->set("interesting_list_iteration", $currIteration, true);
		
		// save updated history list
		$storage->set("interesting_list_sent_history", $sentHistory, true);
		
		// cut the items from the curr list
		$log->debug("Removed " . $toSendCount . " products from interesting products list.");
		$currList = array_slice($currList, $toSendCount);
		$storage->set("current_interesting_list", $currList, true);
	
	}
}