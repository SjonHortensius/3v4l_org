<?php

class PhpShell_Action_Cli_FunctionCallsReparse extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => [
				'full' => 'rescan all existing vld outputs',
				'quick'=> 'scan only scripts that were unparsed until now',
				'hard' => 're-execute vld and rescan all scripts',
			],
			'default' => 'full'
		],
	];
	public static $unknownFunctions = [];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'quick' ? "\"operationCount\" ISNULL" : "true";

		printf(" ** starting with %.3f K functionCalls (mode: %s) **\n", PhpShell_FunctionCall::find()->count()/1000, Basic::$userinput['type']);

		Basic::$database->beginTransaction();
		$set = PhpShell_Input::find($filter, [], ['id' => true]);
		$set->prepareCursor();

		$found = 0;
		/** @var $input PhpShell_Input */
		foreach ($set->fetchNext() as $input)
		{
			$found++;

			if ('hard' == Basic::$userinput['type'] && 'done' == $input->state)
			{
				Basic::$database->q("INSERT INTO queue VALUES (?, ?)", [$input->short, 'vld']);
				$input->waitUntilNoLonger('busy');
			}

			$input->updateFunctionCalls([self::class, 'missingFunctionDefinition']);
			$input->removeCached();

			if (0 == $found%(120*2500))
				printf(" %s %4d K processed | %5d K queries | %3d unknown funcs\n", date('H:i:s'), $found/1000, Basic_Log::$queryCount/1000, count(self::$unknownFunctions));
			elseif (0 == $found%2500)
				print '.';
		}

		Basic::$database->commit();

		printf("\n** completed with %.3f K functionCalls **\n", PhpShell_FunctionCall::find()->count()/1000);

		self::$unknownFunctions = array_filter(self::$unknownFunctions, function($v){ return $v>50; });

		#highest at the end in case we run in screen - we don't see the top
		asort(self::$unknownFunctions);
		print serialize(self::$unknownFunctions);
	}

	public static function missingFunctionDefinition(PhpShell_Input $input, string $name)
	{
		if (!isset(self::$unknownFunctions[$name]))
			self::$unknownFunctions[$name] = 1;
		else
			self::$unknownFunctions[$name]++;
	}
}
