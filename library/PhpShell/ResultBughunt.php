<?php

class PhpShell_ResultBughunt extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'output' => PhpShell_Output::class,
		'version' => PhpShell_Version::class,
	];
	protected static $_numerical = ['maxMemory', 'run'];

	public static function getTable(): string
	{
		return 'result_bughunt';
	}
}