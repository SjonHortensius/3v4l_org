<?php

class PhpShell_Action_About extends PhpShell_Action
{
	public $phpIni;

	public function run()
	{
		#avoid open_basedir @production
		$base = PhpShell_Input::PATH;
		$this->phpIni = `cat $base/../etc/php.ini`;

		return parent::run();
	}
}