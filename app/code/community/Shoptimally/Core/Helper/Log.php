<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Wrap magento log so we can easily disable/enable all logs from Shoptimally.
 */
class Shoptimally_Core_Helper_Log extends Mage_Core_Helper_Abstract
{
	// how many characters of last logs to keep
	const LAST_LOGS_CHAR_COUNT = 5000;
	const LOGS_CACHE_SEPERATOR = "---|---";
	
	// severity level for different type of logs
	const SEVERITY_FATAL = 0;
	const SEVERITY_WARN = 1;
	const SEVERITY_LOG = 2;
	const SEVERITY_DEBUG = 3;
	
	/*
	 * return last part of last logs (LAST_LOGS_CHAR_COUNT characters of log)
	 * */
	public function getLastLogs()
	{
		$ret = Mage::helper('shoptimally_core/storage')->get("last_logs");
		return explode(self::LOGS_CACHE_SEPERATOR, $ret);
	}
	
	
	/*
	 * get the class name of the caller.
	 * */
	private function getCallingClass() 
	{
	
		//get the trace
		$trace = debug_backtrace();
	
		// Get the class that is asking for who awoke it
		// note: 3 is because: caller -> log.debug/log/warn() -> log._writeLog() -> log.formatReport()
		$class = $trace[3]['class'];
	
		// +1 to i cos we have to account for calling this function
		for ( $i=1; $i<count( $trace ); $i++ ) {
			if ( isset( $trace[$i] ) ) // is it set?
				if ( $class != $trace[$i]['class'] ) // is it a different class
				return $trace[$i]['class'];
		}
	}
	
	
    /**
     * debug logs will only appear in debug mode (system.log)
     * @param $text is text to log
     * @param $data is optional object to dump right after log
     */
    public function debug($text, $data=null)
    {
        // tbd this will be a good place to check if in debug mode before calling to log.
        $this->_writeLog($text, $data, self::SEVERITY_DEBUG);
    }
    
    /**
     * warnings logs will always appear in system.log
     * @param $text is text to log
     * @param $data is optional object to dump right after log
     */
    public function warn($text, $data=null)
    {
        // tbd this will be a good place to check if in debug mode before calling to log.
        $this->_writeLog($text, $data, self::SEVERITY_WARN);
    }
    
    /**
     * warnings logs will always appear in system.log
     * @param $text is text to log
     * @param $data is optional object to dump right after log
     */
    public function log($text, $data=null)
    {
        // tbd this will be a good place to check if in debug mode before calling to log.
        $this->_writeLog($text, $data, self::SEVERITY_LOG);
    }
    
    /**
     * actually do the log write.
     * @param $text is text to log
     * @param $data is optional object to dump right after log
     * @param $severity - log sevirity level
     */
    protected function _writeLog($text, $data, $severity)
    {
        // if log disabled skip
        if (!Mage::helper('shoptimally_core/config')->isLogEnabled($severity))
        {
            return;
        }
        
        // format text before writing it
        $severityNames = array("fatal", "warning", "log", "debug");
        $text = $this->formatReport($text, $data, $severityNames[$severity]);
        
        // write to log
        Mage::log($text);
        
        // add to cache of logs
        $this->writeToCachedLog($text);
    }
    
    /**
     * write log to our cached last logs
     * */
    protected function writeToCachedLog($text)
    {
    	$logsHistory = Mage::helper('shoptimally_core/storage')->get("last_logs");
    	$logsHistory = $text . self::LOGS_CACHE_SEPERATOR . $logsHistory;
    	$logsHistory = substr($logsHistory, 0, self::LAST_LOGS_CHAR_COUNT);
    	Mage::helper('shoptimally_core/storage')->set("last_logs", $logsHistory);
    }
    
    /**
     * get text and data, add prefix etc and format the string for the actual report.
     * $text - text to send
     * $data - attached data object
     * $logType - debug / log / warn / ...
     * */
    protected function formatReport($text, $data, $logType)
    {
    	// get class name
    	$className = $this->getCallingClass();
    	
    	// if no class name set "shoptimally"
    	if (is_null($className) || strlen($className) == 0) 
    	{
    		$className = "global";
    	}
    	// if got class name shorten it a bit
    	else
    	{
    		$className = str_replace("_Helper", "", $className);
    		$className = str_replace("_Model", "", $className);
    		$className = str_replace("Shoptimally_", "", $className);
    	}
    	
    	// get date
    	$date = date("m-d H:i:s");
    	
    	// add prefix to report
    	$text = "[Shoptimally-" . $logType . "][" . $className . "] " . $date . " >> " . $text;
    	
    	// add data if exist
    	if (!is_null($data))
    	{	
    		// if data is exception get its message
    		if (is_subclass_of($data, 'Exception') || method_exists($data, "getMessage"))
    		{
    			$text = $text . " -- " . $data->getMessage();
    		}
    		else 
    		{
    			$text = $text . "\r\n" . Mage::helper('core')->jsonEncode($data);
    		}
    	}
    	
    	// return result
    	return $text;
    }
    
    /**
     * report fatal log.
     * note: this will be reported to Shoptimally log only.
     * 
     * @param $text is text to log
     * @param $data is optional object to dump right after log
     * 
     * */
    public function fatal($text, $data=null)
    {
    	// if log disabled skip
    	if (!Mage::helper('shoptimally_core/config')->isLogEnabled(self::SEVERITY_FATAL))
    	{
    		return;
    	}
    	
    	// add prefix
    	$text = date("m-d H:i:s") . " " . $text;
    	 
    	// add data if exist
    	if (!empty($data))
    	{
    		$text = $text . " " . Mage::helper('core')->jsonEncode($data);
    	}
    	
    	// format text and add to cached log
    	Mage::log($text);
    	$this->writeToCachedLog($text);
    }
}