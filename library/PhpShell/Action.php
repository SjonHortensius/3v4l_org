<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $user;

	public function init()
	{
//		$this->user = PhpShell_User::get('_sjon');

		if (in_array($_SERVER['REMOTE_ADDR'], ['37.143.86.26']))
			Basic::$config->PRODUCTION_MODE = false;

		parent::init();
	}

	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if ($hasClass)
			return;

		return 'script';
	}
}