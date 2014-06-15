<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $user;

	public function init()
	{
		if (0)
			$this->user = PhpShell_User::get('_sjon');

		parent::init();
	}

	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if ($hasClass)
			return;

		return 'script';
	}
}
