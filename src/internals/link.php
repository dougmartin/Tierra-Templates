<?php

	/*
	 * 
	 * This is a sample to show how you can have each function in a seperate file.
	 * To do this you name the file the same as the function and to avoid conflicts add a function
	 * prefix that you pass in the virtualDirs option as "functionPrefix"
	 * 
	 * The builtins are automatically searched first when no virtual dir or class name is given
	 * in a call.  This is accomplished with the following virtual dir setting which is prepended
	 * to the list of virtual dirs:
	 * 
	 * $virtualDirs["_"] = array(
	 *    "path" => dirname(__FILE__) . "/internals",
	 *    "classPrefix" => "TierraTemplateInternals_",
	 *    "functionPrefix" => "TierraTemplateInternals_",
	 * );
	 * 
	 */

	function TierraTemplateInternals_Link($text, $href, $options=false) {
		if ($options === false)
			$options = array("href" => $href);
		else
			$options["href"] = $href;
		$attrs = array();
		foreach ($options as $option => $value)
			$attrs[] = "{$option}=\"" . addcslashes($value, '"') . "\"";
		return "<a " . implode(" ", $attrs) . ">" . htmlspecialchars($text) . "</a>";
	}
		