<?php
Mage::helper('shoptimally_core/handleFatals');

/**
 * @package     Shoptimally\Core
 * @version     1.0
 * @author      Shoptimally, Inc.
 * @copyright   Copyright © 2015 Shoptimally, Inc.
 *
 * Controller for our debug data page. We use this controller to debug Shoptimally.
 * URL FOR DEBUG DATA: /shoptimally_debug_data/DebugData/dump
 * URL TO SHOW STORAGE VALUE: /shoptimally_debug_data/DebugData/storage/key/<storage_key>
 * URL TO DELETE STORAGE VALUE: /shoptimally_debug_data/DebugData/delete/key/<storage_key>
 * URL TO FORCE-UPDATE REMOTE CONFIG: /shoptimally_debug_data/DebugData/updateconfig
 * 
 */
class Shoptimally_Core_DebugDataController extends Mage_Core_Controller_Front_Action
{
	// tr's total count
	// this is to do tr background colors
	private $trCount = 0;
	
	// dump a storage value by key
	public function storageAction()
	{
		$storage = Mage::helper('shoptimally_core/storage');
		$key = $this->getRequest()->getParam('key');
		echo $storage->get($key, "KEY DOES NOT EXIST.", false, false);
	}

	// delete storage value by key
	public function deleteAction()
	{
		$storage = Mage::helper('shoptimally_core/storage');
		$key = $this->getRequest()->getParam('key');
		$storage->delete($key);
		echo "Deleted key " . $key;
	}
	
	// force-update configuration from cdn
	public function updateconfigAction()
	{
		try 
		{
			Mage::helper('shoptimally_core/remoteConfig')->updateFromCdn(function($type, $success, $reason)
			{
				if ($success)
				{
					echo "Update " . $type . " done successfully." . "<br />";
				}
				else
				{
					echo "Update " . $type . " Failed! reason: " . $reason . "<br />";
				}
			});
			echo "Done!";
		}
		catch (Exception $e)
		{
			echo $e;
		}
		
		echo "<hr />";
		$remote = Mage::helper('shoptimally_core/remoteConfig');
		$allRemote = $remote->_getAll();
		echo "<h1>LOCAL CONFIG:</h1>";
		echo htmlspecialchars(json_encode ($allRemote['local']));
		echo "<h1>GLOBAL CONFIG:</h1>";
		echo htmlspecialchars(json_encode ($allRemote['global']));
	}
	
	// dump Shoptimally debug data
	public function dumpAction()
	{
		$remote = Mage::helper('shoptimally_core/remoteConfig');
		if( !empty( $debugConfig['hide_debug_page'] ) && $debugConfig['hide_debug_page'] === true )
		{
			return;
		}
		
		// line break tag
		$lb = "<br />";
		
		// open table
		echo "<table style='padding-right:40px;'>";
		
		// get some helpers
		$config = Mage::helper('shoptimally_core/config');
		$storage = Mage::helper('shoptimally_core/storage');
		$user = Mage::helper('shoptimally_core/clientData');
		$log = Mage::helper('shoptimally_core/log');
		
		$currTime = time();
		
		// print version and general config
		try
		{
			$this->printTitle("CONFIG");
			$this->printData("time now", date("Y-m-d H:i:s"));
			$this->printData("timestamp now", $currTime);
			$this->printData("version", $config->getVersion());
			$this->printData("mversion", Mage::getVersion());
			$this->printData("enabled", $config->getIsEnabled(), "bool");
			$this->printData("enabled in admin panel", $config->getGeneralSetting('ShoptimallyEnabled'), "bool");
			$this->printData("log enabled", $config->isLogEnabled(0), "bool");
			$this->printData("log critical (0)", $config->isLogEnabled(0), "bool");
			$this->printData("log warning (1)", $config->isLogEnabled(1), "bool");
			$this->printData("log normal (2)", $config->isLogEnabled(2), "bool");
			$this->printData("log debug (3)", $config->isLogEnabled(3), "bool");
			$this->printData("api key", $config->getApiKey());
			$this->printData("remote config url", Shoptimally_Core_Helper_RemoteConfig::CDN_DOMAIN);
			$this->printData("server url", $config->getServerUrl());
			$this->printData("async js", $config->shouldLoadJsAsync());
			$this->printData("js url", $config->getJsUrl());
			$this->printData("last cron run", $storage->get("last_cron_run", "-", true));
			
			$cacheId = $storage->getCache("cache_id");
			if (is_null($cacheId))
			{
				$cacheId = "Not found, cache was cleared since last visit on this page.";
				$storage->setCache("cache_id", date("Y-m-d H:i:s"));
			}
			$this->printData("cache last known clear", $cacheId);
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'CONFIG'!</h1>";
			echo $e;
		}
		
		// print user related data
		try
		{
			$userEventsUtils = Mage::helper('shoptimally_analytics/userEvents');
			$this->printTitle("USER");
			$this->printData("user id", $user->getUserId());
			$this->printData("user data", $user->_getDataDebug(), "object_pretty");
			$this->printData("current cart", $userEventsUtils->getCartItemsConverted(), "object_pretty");
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'USER'!</h1>";
			echo $e;
		}
		
		// storage debug
		try
		{
			$this->printTitle("STORAGE");
			$keys = $storage->getActiveKeys();
			foreach ($keys as $storageKey)
			{
				$this->printData($storageKey, "storage/key/" . $storageKey, "link");
			}
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'STORAGE'!</h1>";
			echo $e;
		}
		
		try
		{
			// catalog sync data
			$this->printTitle("CATALOG SYNC - OLD");
			$isUsed = $remote->get("catalog_sync_timely_method") === "incremental";
			$this->printData("is used", $isUsed, "bool");
			if ($isUsed)
			{
				$this->printData("catalog sync method", $remote->get("catalog_sync_timely_method"));
				$startCategory = $storage->get("update_last_category");
				$startPage = $storage->get("update_last_page");
				$categoryName = $storage->get("last_category_name");
				$this->printData("curr category", $startCategory);
				$this->printData("curr page", $startPage);
				$this->printData("category name", $categoryName);
			}
			
			$this->printTitle("CATALOG SYNC - NEW");
			
			// interesting list data
			// first get config
			$interestingListConfig = $remote->get("catalog_sync_interesting_list", array(), true);
			
			// get history list history and calc ttl for products
			$historyList = $storage->get("interesting_list_sent_history", array(), true);
			
			// print interesting list stuff
			$this->printData("use interesting list", $remote->get("catalog_sync_interesting_list")["enable"], "bool");
			$this->printData("next interesting list iter", $storage->get("interesting_list_iteration", 0) . "/" . $interestingListConfig["products_ttl"]);
			$this->printData("curr interesting list", $storage->get("current_interesting_list", array(), true), "array");
			$this->printData("history list", $historyList, "array");
			$this->printData("interesting list settings", $interestingListConfig, "object_pretty");
			
			$this->printTitle("CATALOG SYNC - HTMLS");
			$this->printData("send items html", $remote->get("update_items_html"), "bool");
			$this->printData("curr interesting htmls list", $storage->get("htmls_interesting_list", array(), true), "array");
			
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'CATALOG SYNC'!</h1>";
			echo $e;
		}
		
		// print logs
		try
		{
			// last log data
			$this->printTitle("SHOPTIMALLY LOG");
			$lastLogs = $log->getLastLogs();
			$index = 0;
			foreach ($lastLogs as $entry)
			{
				// add color to logs
				$color = "black";
				try
				{
					// normal logs
					if (strpos($entry, "[Shoptimally-log]") !== false)
					{
						$color = "black";
					}
					// debug logs
					else if (strpos($entry, "[Shoptimally-debug]") !== false)
					{
						$color = "#888";
					}
					// warnings (this is usually catched exceptions and weird stuff)
					else if (strpos($entry, "[Shoptimally-warning]") !== false)
					{
						$color = "orange";
					}
					// fatals
					else if (strpos($entry, " warning {") !== false)
					{
						$color = "red";
					}
					$entry = "<font color='" . $color . "'>" . $entry . "</font>"; 
				}
				catch (Exception $e)
				{
				}
				
				// print log line
				$this->printData($index, $entry);
				$index++;
			}
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'SHOPTIMALLY LOG'!</h1>";
			echo $e;
		}
		
		
		// actions we can do with remote config
		try
		{
			$this->printTitle("REMOTE CONFIG ACTIONS");
			$this->printData("Update remote config", "../updateconfig", "link");
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'REMOTE CONFIG ACTIONS'!</h1>";
			echo $e;
		}
		
		// print remote config local
		try
		{

			$allRemote = $remote->_getAll();
			$local = $allRemote["local"];
			
			// start with local config
			$this->printTitle("REMOTE CONFIG LOCAL");
			$this->printData("last update", $storage->get("local_config_last_update"));
			foreach ($local as $key => $value)
			{
				$this->printData($key, $value, "recursive");
			}
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'REMOTE CONFIG LOCAL'!</h1>";
			echo $e;
		}
		
		// print remote config global
		try
		{
			$allRemote = $remote->_getAll();
			$global = $allRemote["global"];
			
			$this->printTitle("REMOTE CONFIG GLOBAL");
			$this->printData("last update", $storage->get("global_config_last_update"));
			foreach ($global as $key => $value)
			{
				$this->printData($key, $value, "recursive");
			}
		}
		catch (Exception $e)
		{
			echo "<h1>ERROR IN SECTION 'REMOTE CONFIG GLOBAL'!</h1>";
			echo $e;
		}
		
		// close table
		echo "</table>";
	}
	
	// echo title line
	protected function printTitle($name)
	{
		$name = "<font color='blue'>" . $name . "</font>";
		$this->printData(" .", " .");
		$this->printData($name, "---");
		$this->printData(" .", " .");
	}
	
	// echo data
	// name / value is obvious
	// type is for special parsing (like booleans or recursive objects)
	protected function printData($name, $value, $type=null)
	{
		// open row
		$trBack = $this->trCount++ % 2 == 0 ? "#eef" : "#def";
		echo "\r\n<tr style='background:".$trBack."'>";
		
		// convert value based on type if provided
		switch ($type)
		{
			case "bool":
				if ($value == true) $value = "true";
				if ($value == false) $value = "false";
				break;
				
			case "object":
				$value = Mage::helper('core')->jsonEncode($value);
				break;
				
			case "object_pretty":
				$value = "<pre>" . json_encode($value, JSON_PRETTY_PRINT) . "</pre>";
				break;

			case "array":
				$value = "array<" . count($value) . ">::" . Mage::helper('core')->jsonEncode($value);
				break;
				
			case "link":
				$value = "<a href='" . $value . "' target='_blank'>" . $value . "</a>";
				break;
				
			case "number":
				if ($value === 0) {$value = "0";}
				break;
				
			case "recursive":
				if (is_array($value))
				{
					echo "\r\n<td style=\"padding-right:40px\">".$name."</td>";
					echo "\r\n<td style=\"padding-right:40px\">...</td>";
					echo "\r\n</tr>";
					foreach ($value as $key => $value_2)
					{
						$this->printData($name."/".$key."/", $value_2, "recursive");
					}
					return;
				}
				break;
		}
		
		// print name and value
		echo "\r\n<td style=\"padding-right:40px\">".$name."</td>";
		echo "\r\n<td style=\"padding-right:40px\">".$value."</td>";
		
		// close row
		echo "\r\n</tr>";
	}
}