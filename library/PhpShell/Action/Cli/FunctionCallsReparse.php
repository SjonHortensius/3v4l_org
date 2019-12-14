<?php

class PhpShell_Action_Cli_FunctionCallsReparse extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => ['full', 'quick', 'hard'],
		],
	];
	public static $unknownFunctions = [];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'quick' ? "\"operationCount\" ISNULL" : "true";

		printf(" ** starting with %.3f K functionCalls **\n", PhpShell_FunctionCall::find()->count()/1000);

		for ($found = $i = 0; ($i==0 || $found >= $i*250); $i++)
		{
			Basic::$database->beginTransaction();

			/** @var $input PhpShell_Input */
			foreach (PhpShell_Input::find($filter, [], ['id' => true])->getPage(1+$i, 250) as $id => $input)
			{
				if ('hard' == Basic::$userinput['type'])
				{
					Basic::$database->query("INSERT INTO queue VALUES (?, ?)", [$input->short, 'vld']);
					$input->waitUntilNoLonger('busy');
				}

				$input->updateFunctionCalls([self::class, 'missingFunctionDefinition']);
				$input->removeCached();

				$found++;
			}

			print '.'.(80==$i%81 ? sprintf(" %7d processed | %5d K queries | %6d unknown funcs\n", $found, Basic_Log::$queryCount /1000, count(self::$unknownFunctions) /1000) : '');
			Basic::$database->commit();
		}

		printf(" ** completed with %.3f K functionCalls **\n", PhpShell_FunctionCall::find()->count()/1000);

		self::$unknownFunctions = array_filter(self::$unknownFunctions, function($v){ return $v>50; });

		#highest at the end in case we run in screen - we don't see the top
		asort(self::$unknownFunctions);
		print_r(self::$unknownFunctions);
	}

	public static function missingFunctionDefinition(PhpShell_Input $input, string $name)
	{
		if (!isset(self::$unknownFunctions[$name]))
			self::$unknownFunctions[$name] = 1;
		else
			self::$unknownFunctions[$name]++;
	}
}
