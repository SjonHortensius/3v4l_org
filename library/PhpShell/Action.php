<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $user;
	public $bodyClass;

	public function init()
	{
		if (isset($_SESSION['userId']))
			$this->user = PhpShell_User::get($_SESSION['userId']);
		elseif (!empty($_COOKIE))
		{
			foreach (array_keys($_COOKIE) as $name)
				setcookie($name, '', strtotime('-1 day'));
		}

		header('Strict-Transport-Security: max-age=31536000');
		header('X-Frame-Options: DENY');

		if (0 && in_array($_SERVER['REMOTE_ADDR'], ['31.201.148.110']))
		{
			$wasOn = Basic::$config->PRODUCTION_MODE;
			Basic::$config->PRODUCTION_MODE = false;

			if ($wasOn)
				Basic::$log->start(get_class(Basic::$action) .'::init');
		}

		if ('application/json' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'application/json';
		elseif ('text/plain' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'text/plain';

		// Since we resolve everything to 'script'; prevent random strings in bodyClass
		if (! Basic::$action instanceof PhpShell_Action_Script)
			$this->bodyClass = Basic::$userinput['action'];

		parent::init();
	}

	protected function _handleLastModified()
	{
		if (isset($_SESSION['userId']))
			return false;

		parent::_handleLastModified();
	}

	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if ($hasClass)
			return;

		return 'script';
	}
}