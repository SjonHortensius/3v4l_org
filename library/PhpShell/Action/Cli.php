<?php

abstract class PhpShell_Action_Cli extends PhpShell_Action
{
	public $contentType = 'text/plain';

	public function init(): void
	{
		if (PHP_SAPI != 'cli')
			throw new Basic_CliAction_SapiNotDetectedException('This action can only be run from the cli');

		if (33 != posix_getuid())
			throw new Basic_CliAction_IncorrectUserException('Please run as http user');

		parent::init();
	}
}