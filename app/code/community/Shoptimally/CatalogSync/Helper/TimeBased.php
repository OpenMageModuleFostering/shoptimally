<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This class responsible for the timely-based catalog sync updates, eg sending random / by order items every
 * hour or so. 
 */
class Shoptimally_CatalogSync_Helper_TimeBased extends Mage_Core_Helper_Abstract
{
	// how many products to update every update batch
	const DEFAULT_UPDATE_PRODUCTS_PAGE_SIZE = 200;
	
	/**
	 * Called every X minutes to update the Shoptimally server with the latest catalog.
	 * This method try to crawl categories by order so that every item has const update cycles.
	 * This is the old way that will be removed soon.
	 */
	public function doTimelyCatalogUpdate()
	{
		 
		// get some core utilities
		$log = Mage::helper('shoptimally_core/log');
		$storage = Mage::helper('shoptimally_core/storage');
		$remoteStorage = Mage::helper('shoptimally_core/remoteConfig');
	
		// get last category and page to continue update from there
		$startCategory = $storage->get("update_last_category");
		$startPage = $storage->get("update_last_page");
	
		// if first run
		if (is_null($startCategory) || empty($startCategory))
		{
			$startCategory = 0;
			$startPage = 0;
		}
	
		// get how many products to send to server, either from remote config or default
		$pageSize = $remoteStorage->get("catalog_sync_products_batch_size");
		if (is_null($pageSize)) {$pageSize = self::DEFAULT_UPDATE_PRODUCTS_PAGE_SIZE;}
		
		// get all categories
		$categoriesCollection = Mage::getModel('catalog/category')
			->getCollection()
			->addAttributeToSelect('name');
	
		// convert to array of categories
		$categories = array();
		$indx = 0;
		foreach($categoriesCollection as $category)
		{
			array_push($categories, $category);
			if ($indx++ > $startCategory) break;
		}
	
		// if category overflows, set back to 0
		if ($startCategory >= count($categories))
		{
			$log->log("Update Catalog: finished cycle, restarting from category 0.");
			$startCategory = 0;
		}
	
		// get current category
		$category = $categories[$startCategory];
		
		// get products in category based on page size and current page
		$productCollection = $category->getProductCollection()
								->setPageSize($pageSize)->setCurPage($startPage);
		

		// get total items count in category
		$totalItemsInCategory = $productCollection->getSize();
			
		// for debug purposes
		$storage->set("last_category_name", $category->getName());
		
		// log report
		$log->log("Update Catalog: start update [category = '" . $category->getName() . "', page = " . $startPage . ", Category progress: " . ($pageSize * $startPage) . "/" . $totalItemsInCategory . "]");
	
		// increase page index (will be saved at the end)
		$startPage++;
	
		// select the attributes we want to get
		$productCollection->addAttributeToSelect('*');
	
		// do the update
		$utils = Mage::helper('shoptimally_catalogsync/utils');
		$productsCount = $utils->sendUpdateToServer($category, $productCollection);		

		$log->log("Update Catalog: finished update [sent " . $productsCount . " items]");
		
		// check if we finished this cateogry
		if ($pageSize * $startPage > $totalItemsInCategory)
		{
			$log->debug("Update Catalog: finished category '" . $category->getName() . "', move to next category.");
			$startCategory++;
			$startPage = 0;
		}
	
		// store new page index and category
		$storage->set("update_last_category", $startCategory);
		$storage->set("update_last_page", $startPage);
	
	}
	
	
	/**
	 * Called every X minutes to update the Shoptimally server with the latest catalog.
	 * This function takes random objects from any categories. This is the new method.
	 */
	public function doTimelyCatalogUpdateRandom()
	{
		// get some core utilities
		$log = Mage::helper('shoptimally_core/log');
		$storage = Mage::helper('shoptimally_core/storage');
		$remoteStorage = Mage::helper('shoptimally_core/remoteConfig');
		
		// get how many products to send to server, either from remote config or default
		$pageSize = $remoteStorage->get("catalog_sync_products_batch_size");
		if (is_null($pageSize) || $pageSize == 0) {$pageSize = self::DEFAULT_UPDATE_PRODUCTS_PAGE_SIZE;}
	
		$collection = Mage::getModel('catalog/product')->getCollection();
		$total_products_in_store = $collection->getSize();

		// calc max page for random
		$maxPage = floor(($total_products_in_store / $pageSize) + 0.5);

		// log
		$log->debug("Update Catalog: prepare to send " . $pageSize . " products to shoptimally (out of total " . $total_products_in_store . " products, " . $maxPage . " pages)");
		
		// random page index
		$pageIndex = rand(0, $maxPage);
		$productCollection = Mage::getModel('catalog/product')->getCollection()
								->setPageSize($pageSize)->setCurPage($pageIndex)
								->addAttributeToSelect('*')->load();
	
		// log report
		$log->log("Update Catalog: send item indexes (not == ids) " . ($pageIndex*$pageSize) . "-" . ($pageIndex*$pageSize+$pageSize) . " [page: " . $pageIndex . "].");
		
		// do the update
		$utils = Mage::helper('shoptimally_catalogsync/utils');
		$actualProductsCount = $utils->sendUpdateToServer(null, $productCollection);
		
		// log report
		$log->log("Update Catalog: update done. actually sent: " . $actualProductsCount . " products.");
		
	}
}