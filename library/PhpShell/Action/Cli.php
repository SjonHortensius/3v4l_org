<?php

abstract class PhpShell_Action_Cli extends PhpShell_Action
{
	public $contentType = 'text/plain';

	public function init()
	{
		if (PHP_SAPI != 'cli')
			throw new Basic_CliAction_SapiNotDetectedException('This action can only be run from the cli');

		parent::init();
	}
}