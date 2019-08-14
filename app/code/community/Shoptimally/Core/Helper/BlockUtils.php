<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This helper provide block-related utilities.
 */
class Shoptimally_Core_Helper_BlockUtils extends Mage_Core_Helper_Abstract
{
    
    // keep track on the current block being rendered
    protected $_curr_block = null;
    
    /**
    * get current block rendered
    */
    public function getCurrentBlock()
    {
        return $this->_curr_block;
    }
    
    /**
    * called whenever a block is rendered to set the current block variable
    */
    public function onBlockRender(Varien_Event_Observer $observer)
    {           
        // Get block instance from event
        $this->_curr_block = $observer->getBlock();
    }
}