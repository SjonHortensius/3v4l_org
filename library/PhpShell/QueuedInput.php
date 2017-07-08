<?php

class PhpShell_QueuedInput extends Basic_Entity
{
/* Still based on input.short and version.name
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'version' => PhpShell_Version::class,
	];
*/

	public static function getTable()
	{
		return 'queue';
	}
}