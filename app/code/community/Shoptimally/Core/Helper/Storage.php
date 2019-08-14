<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Provide a simple key-value persistent(-ish) storage.
 * NOTE!!! DON'T USE LOGS HERE.
 * Shoptimally Logs relay on this class, calling a log from inside set() or get() will result in stack overflow.
 */
class Shoptimally_Core_Helper_Storage extends Mage_Core_Helper_Abstract
{   
	
	// since we have cache AND config, we have a problem deleting keys when cache is enabled.
	// if we delete the cache key when cache is enabled in magento, when we next read it we will
	// still get the config value from config cache. even if we delete the config itself, we don't 
	// touch the config cache. so the solution is instead of deleting we set this special value which
	// mark for us that the value had been deleted, in on next cache clear it will actually take effect.
	const DELETED_VALUE = '-@$__deleted_key__$@-';
	
	// will hold the cache manager
	protected $_cache = null;
	
	// will hold the config manager
	protected $_config = null;
	
	// array of currently active keys, eg keys we get / set.
	// this is for debug purposes.
	protected $_activeKeys = null;
	
	// keys prefix for cache and config storage
	const KEY_PREFIX = "shoptimally/";
	
	// max data we allow to save
	// this is a protection mechanism because if you try to set cache larger than this magento won't
	// say anything, but will just cut the string in the middle. so we want to send a warning and don't save.
	const MAX_DATA_LEN = 65500;
	
    /**
     * init the storage helper.
    */
    public function __construct()
    {
    	// get cache and config managers
        $this->_cache = Mage::app()->getCache();
        $this->_config = new Mage_Core_Model_Config();
        
        // get active keys list
        $this->_activeKeys = $this->get("active_keys", array(), true, false);
    }
    
    // return all the active keys, eg things that shoptimally tried to set/get
    // note: the keys here are without the Shoptimally namespace prefix.
    public function getActiveKeys()
    {
    	return $this->_activeKeys;
    }

    // add a key to the list of active keys
    private  function _addToActiveKeys($key)
    {
    	// if already in list return
    	if (in_array($key, $this->_activeKeys))
    	{
    		return;
    	}
    	
    	// add to list and set it
    	array_push($this->_activeKeys, $key);
    	$this->set("active_keys", $this->_activeKeys, true, false);
    }
    
    // remove a key from the list of active keys
    private function _removeFromActiveKeys($key)
    {
    	// if not in list return
    	if (!in_array($key, $this->_activeKeys))
    	{
    		return;
    	}
    	 
    	// remove to list and set it
	    if(($listKey = array_search($key, $this->_activeKeys)) !== false) {
		    unset($this->_activeKeys[$listKey]);
		}
    	$this->set("active_keys", $this->_activeKeys, true, false);
    }
    
    /**
     * set from cache only
     * */
    public function setCache($key, $value)
    {
    	$key = self::KEY_PREFIX . $key;
    	$keySettings = array(Mage_Core_Model_Config::CACHE_TAG, 'SHOPTIMALLY_STORAGE');
    	$this->_cache->save($value, $key, $keySettings, false);
    }
    
    /**
     * get from cache only
     * */
    public function getCache($key, $default=null)
    {
    	$key = self::KEY_PREFIX . $key;
    	$ret = $this->_cache->load($key);
    	if ($ret === false)
    	{
    		return $default;
    	}
    	return $ret;
    }
    
    /**
     * set a config value into config.
     * this is a simple key-value persistent storage.
     * @param $jsonEncode if true, will json-encode value before returning it.
     * 					Use this option for objects!!!
     * @param $addToActiveKeys if true (default) will add these values to the list of active keys
     */
    public function set($key, $value, $jsonEncode=false, $addToActiveKeys=true)
    {
    	// add to list of active keys
    	if ($addToActiveKeys)
    	{
    		$this->_addToActiveKeys($key);
    	}
    	
    	// convert to full key name (added shoptimally name to avoid collision with other stuff)
    	$key = self::KEY_PREFIX . $key;
    	
    	// do json encoding
    	if ($jsonEncode)
    	{
    		$value = Mage::helper('core')->jsonEncode($value);
    	}
    	
    	// make sure value len is valid
    	if (strlen($value) > self::MAX_DATA_LEN)
    	{
    		Mage::log("Shoptimally notice: tried to set a value too big: '" . $key . "'.");
    		return false;
    	}
    	
    	// put value in cache 
    	$this->_setCacheVal($key, $value);
    	
    	// also store in config cache (for persistency)
    	$this->_config->saveConfig($key, $value);
    	return true;
    }
    
    /**
     * get a config value from config
     * this is a simple key-value persistent storage
     * @param $default will be returned if value doesn't exist
     * @param $jsonDecode if true, will json-decode value before returning it.
     * 					Use this option for objects!!!
     * @param $addToActiveKeys if true (default) will add these values to the list of active keys
     */
    public function get($key, $default=null, $jsonDecode=false, $addToActiveKeys=true)
    {
    	// add to list of active keys
    	if ($addToActiveKeys)
    	{
    		$this->_addToActiveKeys($key);
    	}
    	
    	// convert to full key name (added shoptimally name to avoid collision with other stuff)
    	$key = self::KEY_PREFIX . $key;
    	
    	// try to get from cache
    	$val = $this->_cache->load($key);
    	
    	// this special val is set when value is deleted.
    	// read delete key docs for more info.
    	if ($val === self::DELETED_VALUE) {return $default;}
    	
    	// not found in cache? (cache returns false when item does not exist)
    	if ($val === false)
    	{
    		// try to fetch from config
    		$val = Mage::getStoreConfig($key);
    		
    		// not found in config? return default
    		if (is_null($val)) {return $default;}
    	}
    	
    	// do json decoding
    	if ($jsonDecode)
    	{
    		$val = Mage::helper('core')->jsonDecode($val);
    	}
    	
    	// return value
    	return $val;
    }
    
    /**
     * delete a key from storage
     * @param $removeFromActiveKeys if true (default) will remove the key from the list of active keys
     */
    public function delete($key, $removeFromActiveKeys=true)
    {
    	// add to list of active keys
    	if ($removeFromActiveKeys)
    	{
    		$this->_removeFromActiveKeys($key);
    	}
    	
    	// set value to empty string because annoyingly sometimes magento don't delete values (scoping reasons)
    	$this->set($key, "", false, false);
    	
    	// convert to full key name (added shoptimally name to avoid collision with other stuff)
    	$key = self::KEY_PREFIX . $key;
    	
    	// remove from cache and config
    	//$this->_cache->remove($key);
    	$this->_setCacheVal($key, self::DELETED_VALUE);
    	$this->_config->deleteConfig ($key);
    }
    
    /**
     * set a cahce value (string)
     * */
    private function _setCacheVal($key, $value)
    {
    	$keySettings = array(Mage_Core_Model_Config::CACHE_TAG, 'SHOPTIMALLY_STORAGE');
    	$this->_cache->save($value, $key, $keySettings, false);
    }
}