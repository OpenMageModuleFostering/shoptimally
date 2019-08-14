<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Helper functions to handle cookies
 */
class Shoptimally_Core_Helper_Cookie extends Mage_Core_Helper_Abstract
{

    /**
     * get cookie value
     * @param $name is cookie name to read
     * @param $jsonDecode if true will also decode cookie as json
     * @param $default default to return if cookie not found
     * @return cookie as string or array, depends if you requested json decode
     */
    public function getCookie($name, $jsonDecode=true, $default=null)
    {
        // read cookie
        $ret = Mage::getModel('core/cookie')->get($name);

        // if cookie not found (eg null), return
        if (is_null($ret) || strlen($ret) == 0){
            return $default;
        }

        // decode if needed
        if ($jsonDecode) {
            try {
                $ret = Mage::helper('core')->jsonDecode($ret);
            }
            catch (Exception $e) {
                Mage::helper('shoptimally_core/log')->warn("Exception while parsing cookie '" . $name . "'!", $ret);
                return null;
            }
        }

        // return value
        return $ret;
    }

    /**
     * set cookie value
     * @param $name is cookie name to read
     * @param $value is the value to set
     * @param $jsonEncode if true will encode value as json before setting the cookie
     */
    public function setCookie($name, $value, $jsonEncode=true)
    {

        // if requested, encode as json
        if ($jsonEncode) {
            $value = Mage::helper('core')->jsonEncode($value);
        }

        // set cookie
        // getModel('core/cookie')->set($name, $value, $period, $path, $domain, $secure, $httponly);
        return Mage::getModel('core/cookie')->set($name, $value, time()+86400, '/', NULL, false, false);
    }
}