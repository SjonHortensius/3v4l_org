<?php

class PhpShell_Action_Assert extends PhpShell_Action
{
	public function run()
	{
		$result = self::$db->fetchObject("SELECT * FROM result INNER JOIN version ON version.name = result.version WHERE NOT version.\"isHelper\" AND input = ? AND version = ? AND run = ?", [$input, $version, $run]);

		var_dump($result);die;

		self::$db->preparedExec("INSERT INTO assertion VALUES(?, ?, ?, ?)", [$input, $result->output, $result->exitCode, $user]);
	}
}