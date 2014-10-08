<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $user;

	public function init()
	{
//		$_SESSION['userId']=1;
		if (isset($_SESSION['userId']))
			$this->user = PhpShell_User::get($_SESSION['userId']);

		if (in_array($_SERVER['REMOTE_ADDR'], ['37.143.86.26']))
		{
			$wasOn = Basic::$config->PRODUCTION_MODE;
			Basic::$config->PRODUCTION_MODE = false;

			if ($wasOn)
				Basic::$log->start(get_class(Basic::$action) .'::init');
		}

		if ('application/json' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'application/json';

		parent::init();
	}

	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if ($hasClass)
			return;

		return 'script';
	}
}