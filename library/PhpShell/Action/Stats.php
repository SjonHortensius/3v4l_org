<?php

class PhpShell_Action_Stats extends PhpShell_Action
{
	public $inputPerDay;

	public function run()
	{
		$this->inputPerDay = Basic::$database->query("
SELECT SUBSTRING(DATE_TRUNC('day',created)||'' FROM 0 FOR 11) as date, COUNT(*)
FROM input
WHERE now() - created < '1 year'
GROUP BY date_trunc('day', created)
ORDER BY date_trunc('day', created) DESC;");

		return parent::run();

//Basic::debug(iterator_to_array($this->submitPerDay));
	}
}