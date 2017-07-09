<?php

class PhpShell_Action_Stats extends PhpShell_Action
{
	public $inputPerWeek;

	public function run(): void
	{
		if (Basic::$config->PRODUCTION_MODE)
			throw new PhpShell_Action_Stats_NotAvailableException('Not available');

		$this->inputPerWeek = Basic::$database->query("
SELECT SUBSTRING(DATE_TRUNC('week',created)||'' FROM 0 FOR 11) as date, COUNT(*)
FROM input
WHERE now() - created < '1 year'
GROUP BY date_trunc('week', created)
ORDER BY date_trunc('week', created) DESC;");

		echo Basic::$database->query("
			SELECT
				ip,
				MAX(submit.created) lastSeen,
				SUM(submit.count) submits,
				AVG(penalty) penalty,
				JSON_AGG(input.short) inputs,
				SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128)) * CASE WHEN \"runQuick\" IS NULL THEN 1 ELSE 0.1 END) / 1000000 sleep
			FROM submit

JOIN input ON (input.id = submit.input)
WHERE now()-submit.created < '24 hour'
GROUP BY ip
ORDER BY SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128))) DESC
LIMIT 30;")->show();

		parent::run();
	}
}