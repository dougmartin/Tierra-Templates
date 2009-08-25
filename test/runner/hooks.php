<?php

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