<?php

class PhpShell_Action_Query extends PhpShell_Action
{
	public $userinputConfig = array(
		'bookmark' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'valueType' => 'scalar',
			'required' => true,
			'values' => [
				'versionPerformance' => <<<EOQ
SELECT name "version", ROUND(AVG("systemTime")::numeric, 2) sys, ROUND(AVG("userTime")::numeric, 2) usr, ROUND(AVG("maxMemory")/1024) mem FROM input
JOIN result_current r ON (r.input = input.id AND r.run = input.run)
JOIN version v ON (v.id=r.version)
WHERE input.id IN (SELECT id FROM input ORDER BY RANDOM() LIMIT 250) AND NOT v."isHelper"
GROUP BY v.name
ORDER BY AVG("userTime")+AVG("systemTime") DESC;
EOQ
,
			],
		],
	);

	public function run()
	{
		echo Basic::$database->query(Basic::$userinput->bookmark->values[ Basic::$userinput['bookmark'] ])->show();

//		return parent::run();
	}
}
