<?php

class PhpShell_Action_Assert extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'script' => [
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'valueType' => 'scalar',
			'required' => true,
			'options' => ['minLength' => 5, 'maxLength' => 6],
		],
		'version' => [
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 2],
			'valueType' => 'scalar',
			'required' => true,
		],
		'hash' => [
			'valueType' => 'scalar',
			'required' => true,
		],
	);

	public function run()
	{
		if (!isset($this->user))
			throw new PhpShell_LoginRequiredException('This page requires you to login', [], 404);

		$input = PhpShell_Input::find("short = ?", [Basic::$userinput['script']])->getSingle();
		$version = PhpShell_Version::find("name = ? AND NOT \"isHelper\"", [Basic::$userinput['version']])->getSingle();

		if (Basic::$userinput['hash'] != PhpShell_Assertion::getSubmitHash($input, $version->name));
			throw new PhpShell_Action_Assert_InvalidHashException('The submitted hash is incorrect');

		$result = PhpShell_Result::find("input = ? AND version = ? AND run = ?", [$input, $version, $input->run])->getSingle();
Basic::debug(Basic::$userinput, $result);

		self::$db->preparedExec("INSERT INTO assertion VALUES(?, ?, ?, ?)", [$input, $result->output, $result->exitCode, $user]);
	}
}
