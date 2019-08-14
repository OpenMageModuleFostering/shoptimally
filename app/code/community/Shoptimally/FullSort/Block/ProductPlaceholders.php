<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\FullSort
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This block inject the products placeholder grid used until full-sort products are loaded.
 */
class Shoptimally_FullSort_Block_ProductPlaceholders extends Mage_Catalog_Block_Product_List
{

	/**
	 * Retrieve loaded category collection
	 *
	 * @return Mage_Eav_Model_Entity_Collection_Abstract
	 **/
	protected function _getProductCollection()
	{
		$placeholdersIds = array(404,405,406,407);
		$collection = Mage::getModel('catalog/product')->getCollection()
		->addAttributeToFilter('entity_id', array('in' => $placeholdersIds))
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
}