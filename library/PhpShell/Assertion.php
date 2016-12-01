<?php

class PhpShell_Assertion extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'user' => PhpShell_User::class,
	];
	protected static $_numerical = ['exitCode'];

	// This method is not for security purpose; but only to prevent exposing output.id externally
	public static function getSubmitHash(PhpShell_Input $input, PhpShell_Output $output)
	{
		return gmp_strval(gmp_init(crc32($input->short .':'. $output->hash), 10), 58);
	}
}