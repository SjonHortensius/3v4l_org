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
	public static $unknownFunctions = [];

	public function run(): void
	{
		$filter = Basic::$userinput['type'] == 'quick' ? "\"operationCount\" ISNULL" : "true";

		Basic::$database->beginTransaction();
		$set = PhpShell_Input::find($filter, [], ['id' => true]);
		$set->prepareCursor();

		printf(" ** starting with %.3f K functionCalls (mode: %s) %.3f K inputs **\n", PhpShell_FunctionCall::find()->count()/1000, Basic::$userinput['type'], $set->count()/1000);

		$found = 0;
		/** @var $input PhpShell_Input */
		foreach ($set->fetchNext() as $input)
		{
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
}
