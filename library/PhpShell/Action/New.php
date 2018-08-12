<?php

class PhpShell_Action_New extends PhpShell_Action
{
	public $formSubmit = 'eval();';
	public $userinputConfig = [
		'title' => [
			'valueType' => 'scalar',
			'regexp' => '~^[\x20-\x7e\x80-\xff]*$~',
			'maxLength' => 64,
//			'minLength' => 0,
			'options' => [
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
	];

	public function init(): void
	{
		$this->userinputConfig['version']['values'] = self::getPreviewVersions();

		parent::init();
	}

	// separate method for version.html
	public static function getPreviewVersions(bool $forJson = false): array
	{
		return Basic::$cache->get('quickVersionList:'.intval($forJson), function() use($forJson){
			# exclude all versions that aren't always stored by the daemon
			$v = PhpShell_Version::find("NOT \"isHelper\" OR name LIKE 'rfc-%'", [], ['"isHelper"' => true, 'version.order' => false]);

			$list = iterator_to_array($v->getSimpleList('name', 'name'));

			if (!$forJson)
				return $list;

			$o = [];
			foreach ($list as $v)
			{
				if (in_array(substr($v, 5, 2), ['al', 'be', 'rc']))
					$k = substr($v, 0, 5);
				else
					$k = substr($v, 0, 4);

				if (!isset($o[$k]))
					$o[$k] = [];

				$o[$k] []= substr($v, strlen($k));
			}

			foreach ($o as &$l)
			{
				if (array_sum($l) == array_sum(array_keys($l)))
					$l = max($l);
			}

			return $o;
		}, 30);
	}

	public function run(): void
	{
		$title = Basic::$userinput['title'];
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);

		if (isset(Basic::$userinput['version']))
			$version = PhpShell_Version::byName(Basic::$userinput['version']);

		$penalty = PhpShell_Submit::find("ip = ? AND NOW()- submit.created < ?", [$_SERVER['REMOTE_ADDR'], '1 day'])
			->addJoin(PhpShell_Input::class, "input.id = submit.input")
			->getAggregate("SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128)) * CASE WHEN \"runQuick\" IS NULL THEN 1 ELSE 0.1 END)")
			->fetchColumn(0);

#FIXME include in query above
		$pending = count(PhpShell_Input::find("state = 'busy' AND ip = ?", [ $_SERVER['REMOTE_ADDR'] ])
				->addJoin(PhpShell_Submit::class, "submit.input = input.id"));
		$penalty += 5E6 * $pending;

		usleep($penalty);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == 'busy')
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			if (!$input->runArchived && Basic::$userinput['archived'])
				$input->save(['runArchived' => 1]);

			// Allow upgrading quick>full | quick never has title so store that too
			if (isset($input->runQuick) && !isset($version))
				$input->save(['runQuick' => null, 'title' => $title]);

			// Prevent partially running a full script (because of duplicate result)
			if (!isset($version) || 0 == count($input->getResult($version)))
				$input->trigger($version);
		}
		// No results from ::byHash
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			$source = null;

			// ignore submits from /#preview which have no correct referer
			if (!isset(Basic::$userinput['version']))
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

		if (!isset($version))
		{
			usleep(250 * 1000);
			Basic::$controller->redirect($input->short);
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