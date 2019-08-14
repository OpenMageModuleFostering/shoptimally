<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Return information about current page (only works when called from user
 * requests, not from cronjobs or global events).
 */
class Shoptimally_Core_Helper_PageInfo extends Mage_Core_Helper_Abstract
{
	// contain the ids of all products on current page
	// this is filled by _setProductIdsOnPage(), which is called from the observer
	protected $_productIdsOnPage = array();
	
	// set the product ids on current page
	// should be called only from the observer when products list is loaded
	public function _setProductIdsOnPage($prodctIds)
	{
		$this->_productIdsOnPage = $prodctIds;
	}
	
	/**
	 * return a list with all the product ids on current page
	 * */
	public function getProductIds()
	{
		return $this->_productIdsOnPage;
	}
	

	/**
	 * return the main product on this page (its instance)
	 *
	 * @return product instance.
	 * */
	public function getMainProduct()
	{
		return Mage::registry('current_product');
	}
	
	/**
	 * return the main product on this page (for example when viewing a specific item), or null
	 * if this page does not feature one main product.
	 * 
	 * @return product id.
	 * */
	public function getMainProductId()
	{
		$prod = $this->getMainProduct();
		if (is_null($prod)) {return null;}
		return $prod->getId();
	}
    
	/**
	 * return a dictionary with all the page basic info
	 * contains: category, index (if category view), and type
	 */
	public function getBasicInfo()
	{
		// return data
		$ret = array(
				"category" => $this->getCategory(),
				"type" => $this->getPageType(),
				"index" => $this->getIndex(),
				"main_product_id" => $this->getMainProductId(),
				// product ids comes in the buttom block because its not yet loaded in header.
				//"products" => $this->getProductIds(),
		);
		
		// split category to name and id
		if (!empty($ret["category"]))
		{
			$ret["category_id"] = $ret["category"]->getId();
			$ret["category"] = $ret["category"]->getName();
		}
		return $ret;
	}
	
    /** 
    * get current page category (or null if not exist for this page)
    */
    public function getCategory()
    {
        return Mage::registry('current_category');
    }
    
    /**
    * return general data
    */
    public function getData()
    {           
        return array(
            "controller_name" => Mage::app()->getRequest()->getControllerName(),
            "action_name" => Mage::app()->getRequest()->getActionName(),
            "route_name" => Mage::app()->getRequest()->getRouteName(),
            "module_name" => Mage::app()->getRequest()->getModuleName(),
            );
    }
    
    /**
    * if browsing category pages, return the index of the current page
    */
    public function getIndex()
    {
        return Mage::getBlockSingleton('page/html_pager')->getCurrentPage();
    }
    
    /**
    * return the type of the current page.
    * 
    * @return string: "cms" / "cms_home" / "product" / "category" / "cart"
    */
    public function getPageType()
    {
        
        $product = Mage::registry('current_product');
        $category = Mage::registry('current_category');
        
        if ($product && $product->getId()) {
            // The current page is a product page.
            // If you only want the main product detail page, also check for 
            // Mage::app()->getFrontController()->getAction()->getFullActionName() == 'catalog_product_view'
            // Be aware that a current_product and a current_category can be set at the same time.
            // In that case the visitor is viewing a product in a category.
            return "product";
            
        } elseif ($category && $category->getId()) {
            // The current page is a category page
            // If you only want the category list page, also check for 
            // Mage::app()->getFrontController()->getAction()->getFullActionName() == 'catalog_category_view'
            return "category";
        }
        
        // Check for cart page
        if (Mage::app()->getFrontController()->getAction()->getFullActionName() == 'checkout_cart_index') {
            return "cart";
        }
        
        // Check if it's a CMS page:
        $page = Mage::getSingleton('cms/page');
        if ($page->getId()) {
            // The current page is a CMS page
        
            if ($page->getIdentifier() == Mage::getStoreConfig('web/default/cms_home_page')) {
                return "cms_home";
            }
            return "cms";
        }
        
        // unknown type
        return "unknown";
    }
}