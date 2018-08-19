<?php

class PhpShell_Submit extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];
	protected static $_numerical = ['count'];

	public static function create(array $data = [], bool $reload = false): Basic_Entity
	{
		Basic::$database->query("INSERT INTO submit (input, ip, \"isQuick\") VALUES(:input, :ip, :isQuick)
			ON CONFLICT (input, ip) DO UPDATE SET updated = timezone('UTC'::text, now()), count = submit.count + 1
		", $data);

		return self::getStub($data);
	}
}