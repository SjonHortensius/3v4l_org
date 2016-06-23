<?php

class PhpShell_Submit extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];
	protected static $_numerical = ['count'];

	public static function create(array $data = array())
	{
		# incompatible with parent by design! (no need to return object that is ignored)
		return Basic::$database->query("WITH upsert AS (UPDATE submit SET updated = timezone('UTC'::text, now()), count = count + 1 WHERE input = :input AND ip = :ip RETURNING *)
			INSERT INTO submit SELECT :input, :ip, timezone('UTC'::text, now()), null, 1 WHERE NOT EXISTS (SELECT * FROM upsert)",
			$data);
	}
}