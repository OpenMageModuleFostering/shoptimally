<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\RelatedProducts
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This helper provide the main functionality of the 'Related Products' feature.
 */
class Shoptimally_RelatedProducts_Helper_Main extends Shoptimally_Core_Helper_FeatureBase
{
	// define feature name (see Shoptimally_Core_Helper_FeatureBase for more info)
	const NAME = "RelatedProducts";
	
    // how many related products to get by default
    const DEFAULT_RELATED_PRODUCTS_COUNT = 4;
    
    // this is because after we reset the products collection this event is called again.
    // so we use this var to make sure we are only called once per http request.
    protected $alreadyGot = false;
    
    /**
    * return how many related products we want to show (ideally)
    */
    private function getMaxRelatedProductsCount()
    {   
        return $this->getFeatureConfig("products_to_show_count", self::DEFAULT_RELATED_PRODUCTS_COUNT);
    }
    
    /**
    * return if this feature should work on current page
    */
    private function shouldWorkOnThisPage()
    {
        // now return if enabled and page is a legal category page
        return Mage::helper('shoptimally_core/pageInfo')->getPageType() == "product";
    }
    
    /**
     * get related items for this product from Shoptimally server
     * @param $productId - product id we want related items for
     * @return - list of product ids.
     */
    private function getRelatedProductsFromServer($productId)
    {
        
        // send request to server
        $response = $this->sendAjax("features/related_items/get", array("product" => $productId), 1);
                     
    	// if exception or error skip
        if (is_null($response) || $response->isError()) {
            return null;
        }
            
        // get and return ids list from response
		$idsList = Mage::helper('core')->jsonDecode($response->getBody());
		return $idsList;
    }
        
    /**
     * push the Related Products instead of the original related products collection.
     * this should be called from the observer, after the related products list was loaded.    
     *
     * @param $relatedProductsCollection - the related products list we want to replace.
     */
   	protected function _runFeatureImp($relatedProductsCollection)
    {
   		// if already called this request, skip
   		if ($this->alreadyGot)
   		{
   			return;	
   		}
   		
    	// make sure feature is enabled and should work on this page
    	if (!$this->shouldWorkOnThisPage())
    	{
    		return;
    	}
    	
    	// get current block being rendered and make sure its the related products block
    	$block = Mage::helper('shoptimally_core/blockUtils')->getCurrentBlock();
    	if (empty($block) || $block->getType() != 'catalog/product_list_related')
    	{
    		return;
    	}
    	
    	// this prevents endless recursive updates
    	$this->alreadyGot = true;
    	
    	// get the main product id on this page
    	$productId = Mage::helper('shoptimally_core/pageInfo')->getMainProductId();
    	if (is_null($productId))
    	{
    		$this->reportError("Failed to get main product id!");
    		return;
    	}
    	
    	// get list of items to push (ids) from server
    	$newProductIds = $this->getRelatedProductsFromServer($productId);
        
        // if didn't get anything to show (or exception) stop here
    	if (is_null($newProductIds)) {return;}
        if (empty($newProductIds))
        {
        	$this->reportRejected();
            return;
        }
        
        // get the ids of the original items for feature event
        $originalRelated = clone $relatedProductsCollection;
        
        // get the desired amount of related products to show (this will slice the list if we got more than desired)
        $amount = $this->getMaxRelatedProductsCount();
        $newProductIds = array_slice($newProductIds, 0, $amount);
        
        // convert to a list of products instances
        $productUtils = Mage::helper('shoptimally_core/productsUtils');
        $newProducts = $productUtils->getProducts($newProductIds);
        
        // replace the original related products collection with out related products.
        $productUtils->replaceProducts($relatedProductsCollection, $newProducts);
        $this->reportSuccessReplacement($originalRelated, $newProducts);
    }
}