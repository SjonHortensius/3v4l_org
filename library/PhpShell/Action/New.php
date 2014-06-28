<?php

class PhpShell_Action_New extends PhpShell_Action
{
	public $formSubmit = 'eval();';
	protected $_userinputConfig = array(
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
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			// No results from ::byHash
			$source = null;
			if (preg_match('~^https?://'. preg_quote($_SERVER['HTTP_HOST'], '~').'/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $matches))
				$source = $matches[1];

			try
			{
				$source = PhpShell_Input::get($source);
			}
			catch (Exception $e)
			{
				#care
			}

			$input = PhpShell_Input::create($code, $source);
		}

		PhpShell_Submit::create(['input' => $input->short, 'ip' => $_SERVER['REMOTE_ADDR']]);

		usleep(250 * 1000);
		die(header('Location: /'. $input->short, 302));
	}
}