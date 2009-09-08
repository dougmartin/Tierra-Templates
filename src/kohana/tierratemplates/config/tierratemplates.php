<?php defined('SYSPATH') OR die('No direct access allowed.');

$config = array
(
	'integration'   => TRUE,        // Enable/Disable TierraTemplate integration
	'templates_ext' => 'tpl',
	'options'		=> array(
		"baseTemplateDir" => APPPATH."views/",
		"cacheDir" 		  => APPPATH.'cache/',
	)
);
