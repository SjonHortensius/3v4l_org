<?php

class PhpShell_Operation extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input,
	];
	protected static $_numerical = ['count'];

	public static function create(array $data = array())
	{
		if (isset($data['operand']) && strlen($data['operand']) > 64)
			return false;

		if (isset($data['operand']))
			$opMatch = "operand = :operand";
		else
			$opMatch = "operand ISNULL";

		# incompatible with parent by design! (no need to return object that is ignored)
		return Basic::$database->query("WITH upsert AS (UPDATE operations SET count = count + 1 WHERE input = :input AND operation = :operation AND ".$opMatch." RETURNING *)
			INSERT INTO operations SELECT :input, :operation, :operand, 1 WHERE NOT EXISTS (SELECT * FROM upsert)",
			$data);
	}

	public static function getTable()
	{
		return '"operations"';
	}
}
