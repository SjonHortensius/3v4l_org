<?php

class PhpShell_Result extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'output' => PhpShell_Output::class,
		'version' => PhpShell_Version::class,
	];
	protected static $_numerical = ['maxMemory', 'run'];
}