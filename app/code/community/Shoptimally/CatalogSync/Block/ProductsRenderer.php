<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\CatalogSync
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This block gets a list of product ids and render the products without toolbar and other nonesense.
 * Note however that weather its a single product or multiple products, they will always be wrapped inside
 * the products grid and have classes like "item first" etc, as if its items grid on page.
 */
class Shoptimally_CatalogSync_Block_ProductsRenderer extends Mage_Catalog_Block_Product_List
{
	protected $_productIds = null;
	
	/**
	 * set the list of product ids to render
	 **/
	public function setProductsList($productIds)
	{
		$this->_productIds = $productIds;
		return $this;
	}


	/**
	 * Retrieve loaded category collection
	 *
	 * @return Mage_Eav_Model_Entity_Collection_Abstract
	 **/
	protected function _getProductCollection()
	{
		$collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $this->_productIds))
                ->addAttributeToSelect('*')
				->load();
		return $collection;
	} 
	
	/**
	 * We override this function so we won't dispatch the catalog_block_product_list_collection event.
	 * Note: we must add the toolbar as child because it is used internally to determine how to display
	 * the products. but we still need to not render it somehow.
	 */
	protected function _beforeToHtml()
	{
		$toolbar = $this->getToolbarBlock();
		
		// called prepare sortable parameters
		$collection = $this->_getProductCollection();
		
		// use sortable parameters
		if ($orders = $this->getAvailableOrders()) {
			$toolbar->setAvailableOrders($orders);
		}
		if ($sort = $this->getSortBy()) {
			$toolbar->setDefaultOrder($sort);
		}
		if ($dir = $this->getDefaultDirection()) {
			$toolbar->setDefaultDirection($dir);
		}
		if ($modes = $this->getModes()) {
			$toolbar->setModes($modes);
		}
	 	
		// set collection to toolbar and apply sort
		$toolbar->setCollection($collection);
		$this->setChild('toolbar', $toolbar);
		
		// call the base _beforeToHtml(), while skipping the Mage_Catalog_Block_Product_List::beforeToHtml()
		return Mage_Catalog_Block_Product_Abstract::_beforeToHtml();
	}
	
	/**
	 * Retrieve additional blocks html
	 *
	 * @return string
	 */
	public function getAdditionalHtml()
	{
		return "";
	}
	
	/**
	 * Retrieve list toolbar HTML
	 *
	 * @return string
	 */
	public function getToolbarHtml()
	{
		return "";
	}
}