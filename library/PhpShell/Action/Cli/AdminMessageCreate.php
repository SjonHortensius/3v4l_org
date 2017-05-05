<?php

class PhpShell_Action_Cli_AdminMessageCreate extends PhpShell_Action_Cli
{
	public $userinputConfig = [
		'type' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'values' => ['ban', 'admin'],
		],
		'ip' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'valueType' => 'scalar',
			'regexp' => '~^[0-9.]+$~',
		],
		'message' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 3],
			'valueType' => 'scalar',
		],
	];

	public function run()
	{
		Basic::$cache->set(Basic::$userinput['type'].'Message::'. Basic::$userinput['ip'], Basic::$userinput['message']);

		print('succes');
	}
}
