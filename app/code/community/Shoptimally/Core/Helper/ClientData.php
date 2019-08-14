<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * This helper read the client cookie to get data from it, including things like user id, features list, etc..
 */
class Shoptimally_Core_Helper_ClientData extends Mage_Core_Helper_Abstract
{
    /**
     * Will store the content of the shoptimally_user cookie
     * @var array
     */
    protected $_userCookie;

    /**
     * init the user data.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init the client data.
     */
    protected function init()
    {
        $this->_userCookie = Mage::helper('shoptimally_core/cookie')->getCookie("shoptimally_user", true);
        if (is_null($this->_userCookie)) {$this->_userCookie = array();}
        $this->decodePart('active_features_cache');
    }
    
    /**
     * get all user data for debug
     * */
    public function _getDataDebug()
    {
    	return $this->_userCookie;
    }
    
    /**
    * decode sub-keys in the user cookie that are stringified inside the cookie.
    * eg: {some_field: "{a: 1, b: 2}"}     instead of     {some_field: {a: 1, b: 2}}
    */
    private function decodePart($name)
    {
        if (array_key_exists ($name, $this->_userCookie))
        {
            $decoder = Mage::helper('core');
            $this->_userCookie[$name] = $decoder->jsonDecode($this->_userCookie[$name]);
        }
    }
    
    /**
     * get features list from the cookie.
     * these features are result of the Shoptimally server config + AB test
     */
    public function getEnabledFeatures()
    {
        try
        {
            return $this->_userCookie['active_features_cache']['list'];
        }
        catch (Exception $e) 
        {
            return array();
        }
    }

    /**
     * get user id
     */
    public function getUserId()
    {
        if (array_key_exists("id", $this->_userCookie))
        {
            return $this->_userCookie["id"];
        }
        return null;
    }
}