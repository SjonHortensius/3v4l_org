<?php

class PhpShell_Action_Stats extends PhpShell_Action
{
	public $submitPerDay;

	public function run()
	{
		$this->submitPerDay = Basic::$database->query("
SELECT DATE_TRUNC('day',submit.created), COUNT(*), AVG(input.penalty) penalty, AVG(\"operationCount\") ops
FROM submit JOIN input on (id = input)
GROUP BY date_trunc('day', submit.created)
ORDER BY date_trunc('day', submit.created) DESC;");
	}
}