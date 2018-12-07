<?php

class PhpShell_Action_Cli_FunctionCallsReparse extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => ['full', 'quick', 'hard'],
		],
	];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'quick' ? "\"operationCount\" ISNULL" : "true";

		for ($found = $i = 0; ($i==0 || $found >= $i*250); $i++)
		{
			Basic::$database->beginTransaction();

			/** @var $input PhpShell_Input */
			foreach (PhpShell_Input::find($filter, [], ['created' => false])->getPage(1+$i, 250) as $id => $input)
			{
				if ('hard' == Basic::$userinput['type'])
				{
					Basic::$database->query("INSERT INTO queue VALUES (?, ?)", [$input->short, 'vld']);
					$input->waitUntilNoLonger('busy');
				}

				$input->updateFunctionCalls();
				$input->removeCached();

				$found++;
			}

			print '.'.(80==$i%81 ? sprintf(" %d processed | %d queries\n", $found, Basic_Log::$queryCount) : '');
			Basic::$database->commit();
		}
	}
}
