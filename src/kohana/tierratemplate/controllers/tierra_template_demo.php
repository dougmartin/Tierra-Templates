<?php defined('SYSPATH') OR die('No direct access allowed.');

class Tierra_Template_Controller extends Controller
{
	// Do not allow to run in production
	const ALLOW_PRODUCTION = FALSE;

	public function index()
	{
		$welcome = new View('demo');
		$welcome->message = "Welcome to the Kohana!";

		$welcome->render(TRUE);
	}
	
	public function plain(){
		$view = new View('plain');
		$view->message = "Welcome to the Kohana!";
		$view->render(TRUE);
	}
}
