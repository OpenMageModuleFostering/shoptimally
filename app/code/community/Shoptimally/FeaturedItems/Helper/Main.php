<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\FeaturedItems
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This helper provide the main functionality of the 'featured items' feature.
 */
class Shoptimally_FeaturedItems_Helper_Main extends Shoptimally_Core_Helper_FeatureBase
{
	// define feature name (see Shoptimally_Core_Helper_FeatureBase for more info)
	const NAME = "FeaturedItems";
	
    // default number of products required in collection to initiate this feature.
    // this value is used if remote config don't have this option.
    const DEFAULT_MIN_PRODUCTS_TO_OPERATE_ON = 8;
    
    /**
    * return the minimum amount of required products to run this feature.
    * for example, if 8 and current page only have 5 products, featured items will not run.
    */
    private function getMinProductsRequiredToRun()
    {
        return $this->getFeatureConfig("min_products_to_operate_on", self::DEFAULT_MIN_PRODUCTS_TO_OPERATE_ON);
    }
    
    /**
    * return if this feature should work on current page
    */
    private function shouldWorkOnThisPage($productsCollection)
    {
        // check if there are enough products in this collection
        $minProducts = $this->getMinProductsRequiredToRun();
        if($productsCollection->count() < $minProducts)
        {
            return;
        }
        
        // now return if enabled and page is a legal category page
        return Mage::helper('shoptimally_core/pageInfo')->getPageType() == "category";
    }
    
    /**
     * get featured items we need to push for current user, page, etc.
     * @param $category - category to get items for.
     * @param $index - page index
     * @return - list of product ids.
     */
    private function getFeaturedItemsFromServer($category, $index)
    {
        // get server communication helper
        $server = $this->_server;
        
        # get category url
        $urlUtils = Mage::helper('shoptimally_core/urlUtils');
        $categoryUrl = $urlUtils->toRelative($category->getUrl()); 
        
        // get category id
        $category = Mage::registry('current_category');
        if (!is_null($category)) {
        	$category = $category->getId();
        }
        
        // send request to server
        $data = array(
            	"page_data" => array(
                "index" => $index,
                "base_url" => $categoryUrl,
            	"category_id" => $category,
            )
        );
        $response = $this->sendAjax("features/featured_items/get", $data, 1);
                     
        // if exception happened skip
        if (is_null($response) || $response->isError()) {
            return null;
        }
            
        // get and return ids list from response
        $idsList = Mage::helper('core')->jsonDecode($response->getBody());
        return $idsList;
    }
    
    /**
    * this function get the list of product ids we are about to push, and prepare it. its ultimate goal is to
    * make sure the product rows are nicely aligned.
    * if for example every row of products have 4 items, and we are about to push 3, we will "break"
    * the last line. so we want to avoid it. also keep in mind that some of the new products we are about to push
    * already exist in original collection, so those product just move up and not added twice. 
    * so this function does the following:
    *
    * 1. if there are not enough new items to push that *doesn't already exist*, we remove all the unique new
    *       items from the ids list. this means in this case we will only promote existing products on the page
    *       and won't add new ones.
    * 2. if there are more than needed unique new items, remove the extras, so we'll add the right amount.
    * 
    * @param $newProductIds - list of products ids we want to push.
    * @param $productsCollection - collection to push into.
    * @return - new list of product ids.
    */
    private function fixNewProductsList($newProductIds, $productsCollection)
    {
        // first calculate how many items we want to push based on the items count in original list
        $collectionCount = count($productsCollection);
        if (($collectionCount % 3 == 0) && ($collectionCount % 4 != 0)) {
            $requiredItemsCount = 3;
        }
        else {
            $requiredItemsCount = 4;
        }
            
        
        // iterate over the new products ids and generate two lists:
        // 1. new items that don't exist in original collection.
        // 2. new items that already exist in collection.
        $uniqueIds = array();
        $existingIds = array();
        foreach($newProductIds as $id)
        {
            // check if current id is unique or already appear
            $unique = true;
            foreach($productsCollection as $existingProduct)
            {
                if ($id == $existingProduct->getId())
                {
                    $unique = false;
                    break;
                }
            }
            
            // push to either the unique ids or the existing ids
            if ($unique) {
                array_push($uniqueIds, $id);
            }
            else {
                array_push($existingIds, $id);
            }
        }

        // now since we always return the existing items because we want to promote them,
        // we will iterate over unique items (if have enough) and add them to $existingIds.
        if (count($uniqueIds) >= $requiredItemsCount)
        {
            $insertedCount = 0;
            foreach($uniqueIds as $id)
            {
                array_push($existingIds, $id);
                if (++$insertedCount >= $requiredItemsCount) {break;}
            }
        }
        
        // return the existing ids with the right amount of new unique ids
        return $existingIds;
    }
    
    /**
     * this function do most of the logic:
     * 1. check if should work on this page at all or not, if feature is enabled, etc.
     * 2. get some page info etc, and request items from server.
     * 3. fix featured items to prevent duplications etc.
     * 
     * @param $productsCollection - the loaded products list to push the featured items into.
     * @return list of product ids to push as featured items.
     */
    private function getFeaturedItemsIds($productsCollection)
    {        

        
        // get current page category
        $pageInfo = Mage::helper('shoptimally_core/pageInfo');
        $pageCategory = $pageInfo->getCategory();
        
        // no category on this page? weird, report and return
        if (empty($pageCategory))
        {
            $this->_log->warn("FeaturedItems could not get page category object!", 
                            Mage::helper('shoptimally_core/urlUtils')->getCurrentUrl());
            return;
        }
        
        
        // get list of items to push (ids)
        $newProductIds = $this->getFeaturedItemsFromServer($pageCategory, $pageInfo->getIndex());
                       
        // if don't have anything to show or got exception from getItemsIds(), stop here
        if (is_null($newProductIds) || empty($newProductIds))
        {
            return;
        }
        
        // fix the new products list, read function docs for more info
        $newProductIds = $this->fixNewProductsList($newProductIds, $productsCollection);
    	return $newProductIds;
    }
    
        
    /**
     * push the featured items into the products collection.
     * this should be called from the observer, after the products list was loaded.      
     */
   	private function pushFeaturedItems($productsCollection)
    {
   		
    }
    
    /**
     * run this feature.
     * @param $productsCollection - the loaded products list to push the featured items into.
     */ 
    protected function _runFeatureImp($productsCollection)
    {
    	// make sure we should work on this page
    	if (!$this->shouldWorkOnThisPage($productsCollection))
    	{
    		return;
    	}
    	
    	// get current block being rendered and make sure its "catalog/product_list"
    	// this prevents us from working on things like "recently viewed" and other special blocks.
    	$block = Mage::helper('shoptimally_core/blockUtils')->getCurrentBlock();
    	if (empty($block) || $block->getType() != 'catalog/product_list')
    	{
    		return;
    	}
    	
    	// get ids to push
    	$newProductIds = $this->getFeaturedItemsIds($productsCollection);
    	
    	// test again after fixed new products list if there's nothing new to show
    	if (empty($newProductIds))
    	{
    		$this->reportRejected();
    		return;
    	}
    	
    	// get products utils helper
    	$productUtils = Mage::helper('shoptimally_core/productsUtils');
    	
    	// convert to a list of products instances
    	$newProducts = $productUtils->getProducts($newProductIds);
    	
    	// get original collection for statistics
    	$originalList = clone $productsCollection;
    	
    	// add featured items to collection
    	$productUtils->insertProducts($productsCollection, $newProducts);
    	$this->reportSuccessReplacement($originalList, $productsCollection);
    }
}