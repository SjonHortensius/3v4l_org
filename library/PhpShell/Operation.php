<?php

class PhpShell_Operation extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];
	protected static $_numerical = ['count'];

	public static function create(array $data = [])
	{
		if (isset($data['operand']) && strlen($data['operand']) > 64)
			return false;

		if (!isset($data['count']))
			$data['count'] = 1;

		Basic::$database->query("
			INSERT INTO operations VALUES (:input, :operation, :operand, :count)
				ON CONFLICT ON CONSTRAINT \"operations_inputOp\" DO UPDATE
				SET count = operations.count + 1",
			$data);

		return self::getStub($data);
	}

	public static function getTable()
	{
		return 'operations';
	}
}