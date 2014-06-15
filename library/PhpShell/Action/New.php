<?php

class PhpShell_Action_New extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'code' => [
			'valueType' => 'scalar',
			'inputType' => 'textarea',
			'regexp' => '~<\?~',
		],
	);

	public function run()
	{
//		if (false === strpos($_POST['code'], '<?'))
//			return $this->getError(400);

		$code = Phpshell_Script::clean($_POST['code']);
		$hash = Phpshell_Script::getHash($code);

		try
		{
			$input = Phpshell_Script::byHash($hash);

			if ($input->state == "busy")
				return $this->getError(403);

			$input->trigger();
		}
		catch (Exception $e)
		{
			// No results from ::byHash
			$source = null;
			if (preg_match('~^https?://3v4l.org/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $matches))
				$source = $matches[1];

			$input = Phpshell_Script::create($code, $source);
		}

		self::$db->preparedExec("WITH upsert AS (UPDATE submit SET updated = now(), count = count + 1 WHERE input = :short AND ip = :remote RETURNING *)
			INSERT INTO submit SELECT :short, :remote, now(), null, 1 WHERE NOT EXISTS (SELECT * FROM upsert)",
			[':short' => $input->short, ':remote' => $_SERVER['REMOTE_ADDR']]);

#maintenance mode
#return $this->getError(501);

		usleep(250 * 1000);
		die(header('Location: /'. $input->short, 302));
	}
}