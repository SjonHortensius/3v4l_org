<?php

class PhpShell_Action_Stats extends PhpShell_Action
{
	public $inputPerWeek;
	public $weekStats;

	public function run()
	{
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

		return parent::run();

//Basic::debug(iterator_to_array($this->submitPerWeek));
	}
}