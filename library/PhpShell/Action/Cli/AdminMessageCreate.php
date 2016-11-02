<?php

class PhpShell_Action_Cli_AdminMessageCreate extends PhpShell_Action_Cli
{
	public function run()
	{
		$vars = array_combine(['x', 'x', 'type', 'ip', 'message'], $_SERVER['argv']);
		print_r($vars);

		if (count($_SERVER['argv']) < 3 || !in_array($vars['type'], ['ban', 'admin'], true))
			die('incorrect parameters');

		Basic::$cache->set($vars['type'].'Message::'. $vars['ip'], $vars['message']);

		print('succes');
	}
}
