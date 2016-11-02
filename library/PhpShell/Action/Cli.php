<?php

abstract class PhpShell_Action_Cli extends PhpShell_Action
{
	public function init()
	{
		if (php_sapi_name() != 'cli')
			throw new PhpShell_Action_ImportInput_CliNotDetectedException('This action can only be run from the cli');

		Basic::$config->Template->cachePath = '/tmp/';
		ini_set('display_errors', true);

		parent::init();
	}
}