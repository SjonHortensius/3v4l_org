<?php

class PhpShell_Action_Stats extends PhpShell_Action
{
	public $inputPerWeek;
	public $weekStats;

	public function run()
	{
		if (Basic::$config->PRODUCTION_MODE)
			throw new PhpShell_Action_Stats_NotAvailableException('Not available');

		$lastSunday = date('Y-m-d', strtotime('last monday'));

		$this->inputPerWeek = Basic::$database->query("
SELECT SUBSTRING(DATE_TRUNC('week',created)||'' FROM 0 FOR 11) as date, COUNT(*)
FROM input
WHERE now() - created < '1 year' AND created < '". $lastSunday ."'
GROUP BY date_trunc('week', created)
ORDER BY date_trunc('week', created) DESC;");

		$this->weekStats = array_shift(iterator_to_array(Basic::$database->query("
SELECT COUNT(*) count, AVG(penalty) penalty
FROM input
WHERE now() - created < '1 week';")));

		echo Basic::$database->query("
			SELECT
				ip,
				MAX(submit.created) lastSeen,
				SUM(submit.count) submits,
				AVG(penalty) penalty,
				JSON_AGG(input.short) inputs,
				SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128))) / 1000000 sleep
			FROM submit

JOIN input ON (input.id = submit.input)
WHERE now()-submit.created < '24 hour'
GROUP BY ip
ORDER BY SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128))) DESC
LIMIT 30;")->show();

		return parent::run();
//Basic::debug(iterator_to_array($this->submitPerWeek));
	}
}