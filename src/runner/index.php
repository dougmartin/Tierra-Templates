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

	// load the config
	$options = @include_once "config.php";
	showError($options === false, "Missing config.php");
	
	// validate the config
	foreach (array("srcDir", "baseTemplateDir") as $option)
		showError(!isset($options[$option]), "Missing '{$option}' in config.php");

	// get the relative url from this script
	$scriptDir = dirname($_SERVER["SCRIPT_NAME"]);
	$baseUri = ($questionPos = strpos($_SERVER["REQUEST_URI"], "?")) !== false ? substr($_SERVER["REQUEST_URI"], 0, $questionPos) : $_SERVER["REQUEST_URI"]; 
	$uri = substr(urldecode($baseUri), strlen($scriptDir) > 1 ? strlen($scriptDir) : 0);
	
	// render the page
	showError(!@include_once $options["srcDir"] . "/TierraTemplateRunner.php", "Unable to find TierraTemplateRunner.php in {$options["srcDir"]}");
	try {
		TierraTemplateRunner::Render($uri, $options);
	}
	catch (Exception $e) {
		showError(true, "ERROR: " . $e->getMessage());
	}
	
	// all done
	exit;
	
	function showError($isError, $error) {
		if ($isError) {
			echo $error;
			exit;
		}
	}
	