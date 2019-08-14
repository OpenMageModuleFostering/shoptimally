<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\FeaturedItemsAjax
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Controller for our ajax api, to query products dynamically before showing them as featured items.
 * URL: // shoptimally_featureditemsajax/ajax/getproduct/ids/<product-ids-list>
 * 
 * This feature is kind of unique in a sense that its 99% in javascript and barely have any server code.
 * All the feature analytics etc are from javascript.
 */
class Shoptimally_FeaturedItemsAjax_AjaxController extends Mage_Core_Controller_Front_Action
{
	// return rendered product html from product id(s) (in get request)
	// usage example: /shoptimally_featureditemsajax/Ajax/getProduct/ids/4
	// or: /shoptimally_featureditemsajax/Ajax/getProduct/ids/ids/4,2,6
	public function getProductAction()
	{
		// get product ids from get params
		$productIds = $this->getRequest()->getParam('ids');
		$productIds = explode("," , $productIds);
		
		// create a special block to render the products html
		$block = $this->getLayout()->createBlock('shoptimally_catalogsync/productsRenderer')
									->setTemplate('catalog/product/list.phtml')
									->setProductsList($productIds);
		
		// convert to html and return
		echo $block->toHtml();
	}
}