<?php defined('SYSPATH') OR die('No direct access allowed.');

class Controller extends Controller_Core {
	
	public function _kohana_load_view($template, $vars)
	{
		if ($template == '' || Kohana::config('tierratemplates.integration') == FALSE)
			return;
			
		// Process the view as a Tierra template if the extension matches 
		if (substr(strrchr($template, '.'), 1) === Kohana::config('tierratemplates.templates_ext'))
		{
			// Create the template object
			$options = Kohana::config('tierratemplates.options');
			$options['templateFile'] = str_replace($options['baseTemplateDir'], '', $template);
			$this->MY_TierraTemplate = new MY_TierraTemplate($options);

			// Assign variables to the template
			if (is_array($vars))
			{
				$this->MY_TierraTemplate->setVars($vars);
			}
				
			// Fetch the output
			return $this->MY_TierraTemplate->getOutput();
		}
		
		// process the normal view
		return parent::_kohana_load_view($template, $vars);
	}
}
