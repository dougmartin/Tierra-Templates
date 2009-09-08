<?php defined('SYSPATH') OR die('No direct access allowed.');

include Kohana::find_file('vendor', 'tierratemplates/TierraTemplates');

class MY_TierraTemplate_Core extends TierraTemplate {

	function __construct($options=array())
	{
		// Check if we should use smarty or not
		if (Kohana::config('tierratemplates.integration') == FALSE)
			return;
		
		// Okay, integration is enabled, so call the parent constructor
		parent::__construct($options);
	}

}
