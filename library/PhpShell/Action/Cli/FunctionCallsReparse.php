<?php

class PhpShell_Action_Cli_FunctionCallsReparse extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => [
				'full' => 'rescan all existing vld outputs',
				'quick'=> 'scan only scripts that were unparsed until now',
			],
			'default' => 'full'
		],
	];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'quick' ? "\"operationCount\" ISNULL" : "true";

		// cursor must run in tx - but the vld queue insert must run outside of that
		$dbh = new Basic_Database;
		$dbh->beginTransaction();
		$dbh->q("DECLARE inputCursor CURSOR FOR SELECT * FROM input WHERE {$filter} ORDER BY id DESC");

		function f($n){ return number_format($n, 0, '', ' '); };
		printf(" ** starting with %s functionCalls (mode: %s) %s inputs **\n", f(count(PhpShell_FunctionCall::find())), Basic::$userinput['type'], f(count(PhpShell_Input::find($filter))));

		$found = 0;
		do
		{
			$result = $dbh->q("FETCH NEXT FROM inputCursor");
			$result->setFetchMode(PDO::FETCH_CLASS, PhpShell_Input::class);

			/** @var $input PhpShell_Input */
			$input = $result->fetch();

			if (!$input)
				break;

			$found++;

			try
			{
				$input->getVld();
				$input->removeCached();
			}
			catch (Basic_EntitySet_NoSingleResultException $e)
			{
				//ignore
			}

			if (0 == $found%(120*2500))
				printf(" %s %4d K processed | %5d K queries\n", date('H:i:s'), $found/1000, Basic_Log::$queryCount/1000);
			elseif (0 == $found%2500)
				print '.';
		}
		while (true);

		printf("\n** completed with %s functionCalls | %s K inputs **\n", f(count(PhpShell_FunctionCall::find())), f(count(PhpShell_Input::find($filter))));
	}
}
