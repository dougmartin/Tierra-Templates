<?php
	/*
	 * Tierra Templates - %VERSION%
	 * 
	 * http://tierratemplates.com/
	 *
	 * Copyright (c) 2009 Tierra Innovation (http://tierra-innovation.com)
	 * 
 	 * This project is available for use in all personal or commercial projects under both MIT and GPL2 licenses. 
 	 * This means that you can choose the license that best suits your project, and use it accordingly.
	 * 
	 * MIT License: http://www.tierra-innovation.com/license/MIT-LICENSE.txt
	 * GPL2 License: http://www.tierra-innovation.com/license/GPL-LICENSE.txt
	 * 
	 */

	function onStartupHook($context) {
		echo "<p>Running {$context->startingUri}...</p>";
	}
	
	function outputHook($context, $output) {
		echo "<p>This is the output:</p>{$output}";
	}
	
	function onPreOutputHook($context) {
		if ($context->finalUri == "/testbuiltins.html")
			$context->request->setBlock("footer", "<hr/>This footer is set in the preOutputHook");
	}
	
	function filterOptionsHook($context, $options) {
		// remove the output hook
		unset($options["runnerHooks"]["output"]);
		return $options;
	}