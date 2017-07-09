<?php

class PhpShell_Action_Cli_OperationsReparse extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => ['full'],
		],
	];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'full' ? "true" : "\"operationCount\" ISNULL";

		for ($found = $i = 0; ($i==0 || $found >= $i*250); $i++)
		{
			Basic::$database->beginTransaction();

			foreach (PhpShell_Input::find($filter)->getPage(1+$i, 250) as $id => $input)
			{
				$input->updateOperations();
				$input->removeCached();

				$found++;
			}

			print '.'.(80==$i%81 ? sprintf(" %d processed | %d queries\n", $found, Basic_Log::$queryCount) : '');
			Basic::$database->commit();
		}
	}
}
