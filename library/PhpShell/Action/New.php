<?php

class PhpShell_Action_New extends PhpShell_Action
{
	public $formSubmit = 'eval();';
	public $formTitle = '3v4l.org<small> - online PHP & HHVM shell, run code in 150+ different versions!</small>';
	protected $_userinputConfig = array(
		'title' => [
			'valueType' => 'scalar',
			'regexp' => '~^[\x20-\x7e\x80-\xff]*$~',
			'options' => [
				'maxLength' => 64,
//				'minLength' => 0,
				'placeholder' => 'Untitled',
			],
		],
		'code' => [
			'valueType' => 'scalar',
			'inputType' => 'textarea',
			'regexp' => '~<\?~',
			'default' => "<?php\n\n",
			'required' => true,
		],
	);

	public function run()
	{
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);
#throw new PhpShell_MaintenanceModeException('We are currently in maintenance, read-only mode', [], 503);

		# count(expression) - number of input rows for which the value of expression is not null
		$penalty = Basic::$database->query("
			SELECT
				SUM(submit.count) * 8 * COUNT(input.\"quickVersion\") +
				SUM(submit.count) * 64 * (COUNT(*)-COUNT(input.\"quickVersion\")) +
				AVG(penalty)/128 p
			FROM submit
			JOIN input ON (input.id = submit.input)
			WHERE ip = ? AND now() - submit.created < '24 hour'
		", [ $_SERVER['REMOTE_ADDR'] ])->fetchArray()[0]['p'];

#		if ($penalty > 150*1000)
#			throw new PhpShell_LimitReachedException('You have reached your limit for now, find another free service to abuse', [], 402);

		sleep($penalty/100000);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == 'busy')
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			$input->trigger();
		}
		// No results from ::byHash
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			if (preg_match('~^https?://'. preg_quote($_SERVER['HTTP_HOST'], '~').'/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $match))
				$match = $match[1];

			try
			{
				$source = PhpShell_Input::find("short = ?", [$match])->getSingle();
			}
			catch (Exception $e)
			{
				$source = null;
				#care
			}

			$input = PhpShell_Input::create($code, ['source' => $source, 'title' => Basic::$userinput['title']]);
		}

		PhpShell_Submit::create(['input' => $input->id, 'ip' => $_SERVER['REMOTE_ADDR']]);

		usleep(250 * 1000);
		die(header('Location: /'. $input->short, 302));
	}
}