<?php

	// load the config
	$options = @include_once "config.php";
	showError($options === false, "Missing config.php");
	
	// validate the config
	foreach (array("srcDir", "baseTemplateDir") as $option)
		showError(!isset($options[$option]), "Missing '{$option}' in config.php");

	// get the relative url from this script
	$uri = substr(urldecode($_SERVER["REQUEST_URI"]), strlen(dirname($_SERVER["SCRIPT_NAME"])));
	
	// render the page
	showError(!@include_once $options["srcDir"] . "/TierraTemplateRunner.php", "Unable to find TierraTemplateRunner.php in {$options["srcDir"]}");
	try {
		TierraTemplateRunner::Render($uri, $options);
	}
	catch (Exception $e) {
		showError(true, $e->getMessage());
	}
	
	// all done
	exit;
	
	function showError($isError, $error) {
		if ($isError) {
			echo $error;
			exit;
		}
	}
	