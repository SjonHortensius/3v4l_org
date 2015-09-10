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
		'archived' => [
			'valueType' => 'integer',
			'inputType' => 'checkbox',
			'default' => 0,
			'values' => [
				1 => '+ <span title="Include PHP versions that are older then 3 years">unsupported</span> versions'
			],
		],
	);

	public function run()
	{
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);

		$penalty = Basic::$database->query("
			SELECT SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128))) p
			FROM submit
			JOIN input ON (input.id = submit.input)
			WHERE ip = ? AND now() - submit.created < '1 day'", [ $_SERVER['REMOTE_ADDR'] ])->fetchArray()[0]['p'];

#		if ($penalty > 150*1000)
#			throw new PhpShell_LimitReachedException('You have reached your limit for now, find another free service to abuse', [], 402);
		usleep($penalty);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == 'busy')
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			if (!$input->runArchived && Basic::$userinput['archived'])
				$input->save(['runArchived' => 1]);

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

			$input = PhpShell_Input::create($code, ['source' => $source, 'title' => Basic::$userinput['title'], 'runArchived' => Basic::$userinput['archived']]);
		}

		PhpShell_Submit::create(['input' => $input->id, 'ip' => $_SERVER['REMOTE_ADDR']]);

		usleep(250 * 1000);
		die(header('Location: /'. $input->short, 302));
	}
}