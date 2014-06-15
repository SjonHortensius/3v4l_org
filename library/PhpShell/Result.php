<?php

class PhpShell_Result extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input,
		'output' => PhpShell_Output,
		'version' => PhpShell_Version,
	];
	protected static $_numerical = ['maxMemory', 'run'];
}