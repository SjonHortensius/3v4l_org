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

		printf(" ** starting with %.3f K functionCalls (mode: %s) %.3f K inputs **\n", count(PhpShell_FunctionCall::find())/1000, Basic::$userinput['type'], count(PhpShell_Input::find($filter))/1000);

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

		printf("\n** completed with %.3f K functionCalls | %.3f K inputs **\n", count(PhpShell_FunctionCall::find())/1000, count(PhpShell_Input::find($filter))/1000);
	}
}
