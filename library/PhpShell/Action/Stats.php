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

		echo PhpShell_Submit::find("NOW()- submit.created < ? AND penalty>0", ['1 day'])
			->includePenalties()
			->getAggregate("ip, MAX(submit.created) lastSeen, SUM(submit.count) submits, SUM(penalty) penalties, JSON_AGG(input.short) inputs", "submit.ip", ["SUM(penalty)" => false])
			->show();

		parent::run();
	}
}