<?php

class PhpShell_Action_New extends PhpShell_Action
{
	public $formSubmit = 'eval();';
	protected $_userinputConfig = array(
		'title' => [
			'valueType' => 'scalar',
			'options' => [
				'maxLength' => 64,
				'minLength' => 8
			],
		],
		'code' => [
			'valueType' => 'scalar',
			'inputType' => 'textarea',
			'regexp' => '~<\?~',
			'required' => true,
		],
	);

	public function run()
	{
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);
#throw new PhpShell_MaintenanceModeException('We are currently in maintenance, read-only mode', [], 503);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == "busy")
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

			$input = PhpShell_Input::create($code, $source);
		}

		PhpShell_Submit::create(['input' => $input->id, 'ip' => $_SERVER['REMOTE_ADDR']]);

		if (isset(Basic::$userinput['title'], $this->user))
			$input->save(['title' => Basic::$userinput['title']]);

		usleep(250 * 1000);
		die(header('Location: /'. $input->short, 302));
	}
}
