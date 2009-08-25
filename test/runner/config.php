<?php

	require_once dirname(__FILE__) . "/hooks.php";

	return array(
		"srcDir" => dirname(__FILE__) . "/../../src",
		"cacheDir" => dirname(__FILE__) . "/cache",
		"baseTemplateDir" => dirname(__FILE__) . "/templates",
		"virtualDirs" => array(
			"foo" => array(
				"path" => "externals/foo",
				"functionPrefix" => "__foo_"
			),
			"bar" => array(
				"path" => "externals/bar",
				"functionPrefix" => "__bar_"
			),
			"baz" => array(
				"path" => "externals/baz",
				"classPrefix" => "__baz_"
			),
		),
		"runnerHooks" => array(
			"onStartup" => "onStartupHook",
			"onPreOutput" => "onPreOutputHook",
			"output" => "outputHook",
			"filterOptions" => "filterOptionsHook"
		)
	);