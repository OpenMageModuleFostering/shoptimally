<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Utility functions for CatalogSync, main functionality is to send products data to server
 */
class Shoptimally_CatalogSync_Helper_Utils extends Mage_Core_Helper_Abstract
{
	
    /**
    * send update to server about list of products
    * 
    * @param $category - related category (category that is the source of this update) 
    *                       note: if $category is not provided (eg null), will update ALL
    *                       source categories for this product
    * @param $products - list/collection of products or to update server about
    * @return - number of items successfuly updated (0 if failed to update at all)
    */
    public function sendUpdateToServer($category, $productCollection)
    {
        // get some core utilities
        $log = Mage::helper('shoptimally_core/log');
        $urlUtils = Mage::helper('shoptimally_core/urlUtils');
        $productsUtils = Mage::helper('shoptimally_core/productsUtils');
        $server = Mage::helper('shoptimally_core/server');
                    
        // get site currency
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        
        // get category url
        if (is_null($category))
        {
            $categoryUrl = "";
        }
        else
        {
            $categoryUrl = $urlUtils->toRelative($category->getUrl());
        }
        
        // prepare data to send items in current category and page
        $dataToSend = array(
            "normalized_source_url" => $categoryUrl,
            "items" => array(),
        );
        
        // for logging at the end
        $productsCount = 0;
        
        // Now you can loop through your collection
        foreach($productCollection as $product) {
            
            // increase products count (for logging at the end)
            $productsCount++;
            
            // get product data to send
            $productData = $productsUtils->getProductFullData($product);
            
            // if didn't get a specific source category, get all source categories urls
            if (is_null($category))
            {
                // get product categories to get all categories urls
                $categoriesUrls = array();
                $cats = $product->getCategoryIds();
                foreach ($cats as $category_id) {
                    $_cat = Mage::getModel('catalog/category')->load($category_id);
                    array_push($categoriesUrls, $urlUtils->toRelative($_cat->getUrl()));
                }
                
                // add special all-categories update field
                $productData['all_src_urls'] = $categoriesUrls;
            }
            
            // add currency
            $productData['currency'] = $currencyCode;
            
            // push into data to send to server
            array_push($dataToSend["items"], $productData);
        }
        
        // log report
        if (is_null($category))
        {
            $log->debug("Update Catalog: send " . $productsCount . " products.");
        }
        else
        {
            $log->debug("Update Catalog: send " . $productsCount . " products from category '" . $category->getName() . "'.");
        }
        
        // send update to server
        $response = $server->sendRequest("sites/update_products/", $dataToSend, 240);
                 
        // handle errors from server
        if (is_null($response) || $response->isError()) {
            Mage::helper('shoptimally_core/log')->warn(
                "Failed to update server with catalog data!",
                $response);
            return 0;
        }
        
        // success!
        return $productsCount;
    }
}