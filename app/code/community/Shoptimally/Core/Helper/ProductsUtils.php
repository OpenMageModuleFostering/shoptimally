<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * General products and products-collection utilities.
 */
class Shoptimally_Core_Helper_ProductsUtils extends Mage_Core_Helper_Abstract
{
    /**
     * insert a list of products into the begining of a given collection
     * 
     * @param $collection - collection to insert products into.
     * @param $newProducts - products to insert.
     * 
     * Note! if product already exist in collection it will not push it twice,
     * it will just move it to the begining of the list
     */
    public function insertProducts($collection, $newProducts)
    {   
        // store all previous products in $oldProducts
        $oldProducts = $collection->getItems();
        
        // remove all previous products
        // note: using clear() raise exception
        foreach ($collection as $key => $item) {
            $collection->removeItemByKey($key);
        }
        
        // insert the new products
        foreach ($newProducts as $product) {
            $collection->addItem($product);
        }
        
        // re-add the original products after the new products
        foreach ($oldProducts as $oldProduct) {
            
            // make sure product don't exist in new products list
            $skip = false;
            foreach ($newProducts as $newProduct)
            {
                if ($newProduct->getId() == $oldProduct->getId())
                {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            // add the old product back into collection
            $collection->addItem($oldProduct);
        }
    }
    
    /**
     * totally replace all the products in the given collection with $newProducts
     *
     * @param $collection - collection to replace products.
     * @param $newProducts - products to insert.
     *
     */
    public function replaceProducts($collection, $newProducts)
    {
    	// remove all previous products
    	// note: using clear() raise exception
    	foreach ($collection as $key => $item) {
    		$collection->removeItemByKey($key);
    	}
    
    	// insert the new products
    	foreach ($newProducts as $product) {
    		$collection->addItem($product);
    	}
    	
    }
    
    /**
     * get product instance from id
     * 
     * @param $id - product id to get
     * @return product instance 
     */
     public function getProduct($id)
     {
        $product = Mage::getModel('catalog/product');
        $product->load($id);
        return $product; 
     }
     
     /**
      * get a list of products from a list of ids
      * 
      * @param $idsList - list of ids to get.
      * @param $loadAll - if true will also select * and call collection->load();
      * @return collection of products. 
      *         note: if one or more ids do not exist, they will not be included in the returned list.
     */
     public function getProducts($idsList, $loadAll=true)
     {
     	// get collection
     	$ret = Mage::getModel('catalog/product')->getCollection()
							     	->addAttributeToFilter('entity_id', array('in' => $idsList));
     	
     	// load all attributes
     	if ($loadAll)
     	{
     		$ret = $ret->addAttributeToSelect('*')->load();
     	}
     	
     	// return collection
     	return $ret;
     }
     
     /**
      * get a list of all associated product ids for given product.
      * associated ids can be children for configurable product, products in bundle, etc..
      * 
      * @return either a list with associated ids, or null if not relevant.
      * */
     private function getAssociatedIds($product)
     {
     	// get accociated products if grouped or configurable product
     	$associatedProductIds = array();
     	switch ($product->getTypeId())
     	{
     		// get associated products for grouped product
     		case "grouped":
     			$associated = $product->getTypeInstance(true)->getAssociatedProducts($product);
     			if (!is_null($associated) && $associated) 
     			{
     				foreach ($associated as $associate)
     				{
     					array_push($associatedProductIds, $associate->getId());
     				}
     			}
     			break;
     	
     		// get products in bundle
     		case "bundle":
     			$associatedProductIds = $product->getTypeInstance(true)->getOptionsIds($product);
     			break;
     			
     		// get children for configurable
     		case "configurable":
     			$associated = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
     			if (!is_null($associated) && $associated)
     			{
     				foreach ($associated as $associate)
     				{
     					array_push($associatedProductIds, $associate->getId());
     				}
     			}
     			break;
     			
     		// unrelevant type
     		default:
     			return null;
     	}
     	
     	// return the associated ids
     	return $associatedProductIds;
     }
     
     /**
     * return a dictionary with all the interesting info of a product
     */
     public function getProductFullData($product)
     {
     	// get url utils helper
        $urlUtils = Mage::helper('shoptimally_core/urlUtils');
        
        // this is to get parent product ids later
        $product->loadParentProductIds();
        
        // get product type and associated ids
        $productTypeId = $product->getTypeId();
        $associatedProductIds = $this->getAssociatedIds($product);
        
        // all basic fields we want to get
        // key is the field name in output array
        // value is array of (funcName, defaultValue, exceptionValue)
        $fields = array(
        		'unique_id' => array('getId', "invalid id", "ERROR"),
        		'item_name' => array('getName', "", "ERROR"),
        		'url' => array('getProductUrl', "", "ERROR"),
        		'price' => array('getFinalPrice', -1, -2),
        		'parent_ids' => array('getParentProductIds', array(), "ERROR"),
        		'category_ids' => array('getCategoryIds', array(), "ERROR"),
        		'original_price' => array('getPrice', -1, -2),
        		'special_price' => array('getSpecialPrice', -1, -2),
        		'weight' => array('getWeight', 0, 0),
        		'sku' => array('getSku', null, "ERROR"),
        		'is_in_stock' => array('isInStock', false, false),
        		'created_at' => array('getCreatedAt', null, "ERROR"),
        		'updated_at' => array('getUpdatedAt', null, "ERROR"),
        		'image_url' => array('getImageUrl', "", "ERROR"),
        		'short_description' => array('getShortDescription', "", "ERROR"),
        		'status' => array('getStatus', 2, "ERROR"),
        );
        
        // get all fields
        $ret = array();
        foreach ($fields as $fieldName => $fieldData)
        {
        	$ret[$fieldName] = $this->_getProdValSafe($product, $fieldData[0], $fieldData[1], $fieldData[2]);
        }
        
        // some extra field processing
        $ret['url'] = $urlUtils->toRelative($ret['url']);
        $ret['price'] = intval($ret['price']);
        $ret['special_price'] = intval($ret['special_price']);
        $ret['original_price'] = intval($ret['original_price']);
        $ret['price'] = intval($ret['price']);
        $ret['associated_products'] = $associatedProductIds;
        $ret['product_type'] = $productTypeId;
        
        // determine if product is visible in store
        try {
        	$ret['is_in_store'] = $this->isProductVisible($product);
        }
        catch (Exception $e) {
        	$ret['is_in_store'] = false;
        }
        
        // get stock item data
        try 
        {
	        $stockItem = $product->getStockItem();
	        if (!is_null($stockItem))
	        {
	        	$ret['is_stock_item_in_stock'] = $stockItem->getIsInStock();
	        }
        }
        catch (Exception $e) {$ret['is_stock_item_in_stock'] = "ERROR";}
        
        // get the extra fields from remote config
        $extraFields = Mage::helper('shoptimally_core/remoteConfig')->get("extra_product_fields", array());
        foreach ($extraFields as $extra)
        {
        	$ret[$extra] = $this->getProductAttr($product, $extra);
        }
        
        return $ret;
     }
     
     /*
      * This code requires some explaination:
      * Basically we have a safe method to get product attribute while testing if they exist first.
      * However, what about Magento built-ins?
      * For example, the function getImageUrl() can throw exception if there are no images.
      * there are hundreds of special cases, depending on the state of the product and what it has, and I
      * don't trust Magento to not raise exceptions on things instead of returning null.
      * so this function wraps up getting product data from function in a safe way.
      * @param $product - product instance.
      * @param $function - the name of the function (string) to get.
      * @param $defaultValue - optional value if getting null.
      * @return - either the value, or $onException value if had an exception.
      * */
     protected function _getProdValSafe($product, $function, $defaultValue=null, $onException="[error]")
     {
     	try 
     	{
     		$ret = $product->{$function}();
     		if (is_null($ret))
     		{
     			return $defaultValue;
     		}
     		return $ret;
     	}
     	catch (Exception $e)
     	{
     		return $onException;	
     	}
     }
     
     /**
      * get a single attribute from a product as text.
      * @param $product - the product to get attribute from.
      * @param $attr - the attribute to get.
      * @param $default - return value if non existence 
      * */
     public function getProductAttr($product, $attr, $default=null)
     {
     	// get attribute if existing
     	$attribute = $product->getResource()->getAttribute($attr);
     	if ($attribute)
     	{
     		return $attribute->getFrontend()->getValue($product);
     	}
     	
     	// return default
     	return $default;
     }
     
     /**
     * get all cart items
     * @return list of items from cart (as received from magento)
     *          these are quota items, not products.
     */
     public function getCartItems()
     {
         // note: getAllVisibleItems() return only the added items without parents
         // getAllItems() return ALL quota items.
         return Mage::getModel('checkout/cart')->getQuote()->getAllItems();
     }
     
     /**
     * get the total price of the cart, with tax and discounts etc included.
     */
     public function getCartTotal()
     {
		$quote = Mage::getModel('checkout/session')->getQuote();
		$quoteData = $quote->getData();
		if (array_key_exists('grand_total', $quoteData))
		{
			return $quoteData['grand_total'];
		}
		else
		{
			return 0;
		}
     }
     
     /**
     * get all attributes of a product.
     * you can later use it like this:
     * 
     * $attributes = $prodUtils->getAllAttributes($product));
     * foreach ($attributes as $attribute) {
     *       _log($attribute->getName() . " = " . $attribute->getFrontend()->getValue($product));
     *   }
     */
     public function getAllAttributes($product)
     {
        return $product->getAttributes();
     }
     
     /**
     * return true only if product is truely visible to customers, eg in catalog
     * or search, in stock, is valid etc.
     */
     public function isProductVisible($product)
     {
        return 	$product->isVisibleInCatalog() && $product->isVisibleInSiteVisibility() &&
        		$product->isInStock();
     }
}