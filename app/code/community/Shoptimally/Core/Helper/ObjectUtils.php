<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Utilities and helper functions to work with arrays and other objects 
 */
class Shoptimally_Core_Helper_ObjectUtils extends Mage_Core_Helper_Abstract
{
	/**
	 * get from array with default value (if key doesn't exist will return default).
	 * this should never cause exception or fatal.
	 * @param $array - array to get from.
	 * @param $key - key to search and get.
	 * @param $default - value to return if not found, default to null.
	 * @return - either value from array, or default if not found
	 * */
	public function array_get($array, $key, $default=null)
	{
		if (array_key_exists($key, $array)) {
			return $array[$key];
		}
		return $default;
	}
	
	/**
	 * extract one array from another, based on list of keys.
	 * for example, if you have one array with keys (a,b,c,d) and you want
	 * to extract only keys and values of a, b, this function helps you do that.
	 * in addition it support default values for keys that doesn't exist.
	 * @param $srcArray - array to extract from.
	 * @param $keys - array of keys to extract.
	 * @param $default - value to use if key not found.
	 * @return - extracted array.
	 * */
	public function array_extract($srcArray, $keys, $default=null) 
	{
		$ret = array();
		foreach ($keys as $key)
		{
			$ret[$key] = $this->array_get($srcArray, $key, $default);
		}
		return $ret;
	}
}