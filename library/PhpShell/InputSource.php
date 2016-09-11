<?php

class PhpShell_InputSource extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];

	public static function create(array $data = [])
	{
		Basic::$database->query(
			"INSERT INTO input_src VALUES(:input, :raw)",
			$data);

		return self::getStub($data);
	}

	public static function getTable()
	{
		return 'input_src';
	}
}