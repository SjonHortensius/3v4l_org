<?php

class PhpShell_Action_Search extends PhpShell_Action_Script
{
	public function getSearch($operation, $operand = null)
	{
		$entries = self::$db->fetchObjects('
			SELECT
				short as input,
				input."operationCount",
				input.run run,
				operations.operation
				AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance
			FROM input
			JOIN result ON (result.input = input.short)
			WHERE input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)
			GROUP BY input.short
		');

		TooBasic_Template::show('search', ['results' => $results]);
	}
}