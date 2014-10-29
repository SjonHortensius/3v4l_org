<?php

class PhpShell_Assertion extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input,
		'output' => PhpShell_Output,
		'user' => PhpShell_User,
	];
	protected static $_numerical = ['exitCode'];

	public static function getSubmitHash(PhpShell_Input $input, $versionName)
	{
		// We're lazy about $versionName being valid
		return gmp_strval(gmp_init(crc32($input->short .':'. $versionName .':'. $input->run), 10), 58);
	}
}