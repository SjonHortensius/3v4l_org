<?php

class PhpShell_Reference extends PhpShell_Entity
{
	protected static $_relations = [
		'parent' => PhpShell_Reference::class,
	];

	public static function getTable()
	{
		return 'references';
	}
}