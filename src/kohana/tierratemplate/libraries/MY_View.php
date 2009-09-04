<?php defined('SYSPATH') OR die('No direct access allowed.');

class View extends View_Core {

	public function __construct($name, $data = NULL, $type = NULL)
	{
		$tierratemplate_ext = Kohana::config('tierratemplate.templates_ext');

		if (Kohana::config('tierratemplate.integration') == TRUE AND Kohana::find_file('views', $name, FALSE, (empty($type) ? $tierratemplate_ext : $type)))
		{
			$type = empty($type) ? $tierratemplate_ext : $type;
		}
		parent::__construct($name, $data, $type);
	}
}
