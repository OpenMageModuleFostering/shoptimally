<?php

// this code snippet catches fatal errors and log them right before
// NOTE!!! this code doesn't catch just Shoptimally fatals, it caches ANY fatal.
// because of that we report it as general fatal exception and not as shoptimally log.
// in addition if you see this report in action it doesn't necessarily means Shoptimally has a problem.
// it might be another module.
function ShoptimallyLogFatalErrors()
{
	$error = error_get_last();
	if (!is_null($error))
	{
		// skip this fatal as its built-in in magento (its actually a notice but called a lot when
		// working localhost and if in dev mode this might generate fake fatals. so we don't want to
		// spam tests).
		if (strpos ($error["message"], "vsprintf(): Too few arguments") === 0)
		{
			return;
		}
		
		// report fatal
		Mage::helper('shoptimally_core/log')->fatal("warning", $error);
	}
}
register_shutdown_function("ShoptimallyLogFatalErrors");

class Shoptimally_Core_Helper_HandleFatals extends Mage_Core_Helper_Abstract
{	
}