<?php

class PhpShell_Action_About extends PhpShell_Action
{
	public $phpIni;

	public function run()
	{
		$this->phpIni = file_get_contents(PhpShell_Input::PATH.'../etc/php.ini');

		return parent::run();
	}
}