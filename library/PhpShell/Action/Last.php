<?php

class PhpShell_Action_Last extends PhpShell_Action
{
	public $entries;

	public function run()
	{
		$this->entries = new PhpShell_ScriptsList(PhpShell_Input, 'input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)');
/*		$this->entries = Basic::$database->query('
			SELECT
				short as input,
				input."operationCount",
				input.run run,
				AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance
			FROM input
			JOIN result ON (result.input = input.short)
			WHERE input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)
			GROUP BY input.short
		', []);
*/
		parent::run();
	}
}