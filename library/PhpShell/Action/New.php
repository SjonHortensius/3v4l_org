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
		'version' => [
			'inputType' => 'select',
			'values' => [],
		],
		'archived' => [
			'valueType' => 'integer',
			'inputType' => 'checkbox',
			'default' => 0,
			'values' => [
				1 => 'eol versions'
			],
		],
	);

	public function init()
	{
		$this->_userinputConfig['version']['values'] = Basic::$cache->get('quickVersionList', function(){
			# exclude all versions that aren't always stored by the daemon
			$v = PhpShell_Version::find("NOT name IN('vld', 'segfault', 'hhvm-bytecode')", [], ['"isHelper"' => true, 'version.order' => false]);

			return $v->getSimpleList('name', 'name');
		}, 30);

		parent::init();
	}

	public function run()
	{
		$title = Basic::$userinput['title'];
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);

		// parse phpt format
		if (preg_match('~^--TEST--\n(.*)\n(?:\n--SKIPIF--\n.*)?--FILE--\n(.*)(?:\?>)?\n--EXPECT--\n(.*)$~s', $code, $m))
		{
			$title = substr($m[1], 0, 64);
			$code = $m[2];

			$hash = PhpShell_Input::getHash($code);
			// Match format from daemon
			$assertOutputHash = base64_encode(sha1($m[3], true));
		}

		if (isset(Basic::$userinput['version']))
			$version = PhpShell_Version::byName(Basic::$userinput['version']);

		$penalty = Basic::$database->query("
			SELECT SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128)) * CASE WHEN \"runQuick\" IS NULL THEN 1 ELSE 0.1 END) p
			FROM submit
			JOIN input ON (input.id = submit.input)
			WHERE ip = ? AND now() - submit.created < ?", [ $_SERVER['REMOTE_ADDR'], '1 day' ])->fetchArray()[0]['p'];

		usleep($penalty);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == 'busy')
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			if (!$input->runArchived && Basic::$userinput['archived'])
				$input->save(['runArchived' => 1]);

			// Allow upgrading quick>full
			if (isset($input->runQuick) && !isset($version))
				$input->save(['runQuick' => null]);

			// Prevent partially running a full script (because of duplicate result)
			if (!isset($version) || 0 == $input->getResult($version)->getCount(null, true))
				$input->trigger($version);
		}
		// No results from ::byHash
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			$source = null;

			// ignore submits from /#preview which have no correct referer
			if (!isset($version))
			{
				if (preg_match('~^https?://'. preg_quote($_SERVER['HTTP_HOST'], '~').'/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $match))
					$match = $match[1];

				try
				{
					// Useless when no matches ($match=[]) or submitted from homepage ($match='')
					if (!empty($match))
						$source = PhpShell_Input::find("short = ?", [$match])->getSingle();
				}
				catch (Exception $e)
				{
					#care
				}
			}

			$input = PhpShell_Input::create([
				'code' => $code,
				'source' => $source,
				'title' => $title,
				'runArchived' => Basic::$userinput['archived'],
				'runQuick' => $version,
			]);
		}

		if (isset($assertOutput))
		{
			PhpShell_Assertion::create([
				'input' => $input,
				'outputHash' => $assertOutput,
				'user' => $this->user,
			]);
		}

		if (!isset($version))
		{
			usleep(250 * 1000);
			die(header('Location: /'. $input->short, 302));
		}

		//BUG? this will return directly for non-new inputs
		$input->waitUntilNoLonger('busy');
		usleep(150 * 1000);

		$this->input = $input;
		$this->result = $this->input->getResult($version)->getSingle();
		$this->output = htmlspecialchars($this->result->output->getRaw($input, $version), ENT_SUBSTITUTE);

		$this->showTemplate('quick');
	}
}