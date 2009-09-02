<?php defined('SYSPATH') OR die('No direct access allowed.');

class Controller extends Controller_Core {
	
	public function _kohana_load_view($template, $vars)
	{
		if ($template == '' || Kohana::config('tierratemplate.integration') == FALSE)
			return;
			
		$options = Kohana::config('tierratemplate.options');
		$options['templateFile'] = str_replace($options['baseTemplateDir'], '', $template);
			
		$this->MY_TierraTemplate = new MY_TierraTemplate($options);
		
		if (substr(strrchr($template, '.'), 1) === Kohana::config('tierratemplate.templates_ext'))
		{
			// Assign variables to the template
			if (is_array($vars) AND count($vars) > 0)
			{
				foreach ($vars AS $key => $val)
				{
					$this->MY_TierraTemplate->$key = $val;
				}
			}
			// Fetch the output
			$output = $this->MY_TierraTemplate->getOutput();
		}
		else
		{
			$output = parent::_kohana_load_view($template, $vars);
		}

		return $output;
	}
}
