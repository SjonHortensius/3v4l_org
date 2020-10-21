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
				'aria-label' => 'Optional title',
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
		return Basic::$cache->lockedGet('quickVersionList:'.intval($forJson), function() use($forJson){
			# exclude all versions that aren't always stored by the daemon
			$v = PhpShell_Version::find("NOT \"isHelper\" OR name LIKE 'rfc-%' OR name LIKE 'git-%'", [], ['"isHelper"' => true, 'version.order' => false]);

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
		}, 150);
	}

	public function run(): void
	{
		$title = Basic::$userinput['title'];
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);

		# don't store any more empty submits
		if ($code === '<?php')
		{
			usleep(500 * 1000);
			Basic::$controller->redirect('kuLmD');
		}

		if (isset(Basic::$userinput['version']))
			$version = PhpShell_Version::byName(Basic::$userinput['version']);

		# the second submit.created check is 'useless' but makes pg match the submitRecent index greatly lowering query time
		$stats = PhpShell_Submit::find("ip = ? AND NOW()- submit.created < ? AND submit.created > ?", [$_SERVER['REMOTE_ADDR'], '1 day', date('Y-m-d', strtotime('-1 day'))])
			->includePenalties()
			->getAggregate()
			->fetchArray();
		$stats = current(iterator_to_array($stats));
		$penalty = 1000 * ($stats['agePenalty'] * $stats['weightPenalty'] * $stats['busyPenalty']);

		if ($penalty > 9E6)
			throw new PhpShell_TemporaryBlockedException('It seems you are submitting too many scripts - please come back later. Is this incorrect? Contact me! [penalty:%1.2f:%1d:%1d]', [$stats['agePenalty'], $stats['weightPenalty'], $stats['busyPenalty']], 402);

		usleep($penalty);

		try
		{
			$input = PhpShell_Input::byHash($hash);

			if ($input->state == 'busy')
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');
			if ($input->state == 'abusive') # the submitter doesn't necessarily know why his submit is abusive, forward to results
				Basic::$controller->redirect($input->short);

			$input->runArchived = (bool)Basic::$userinput['archived'];
			$input->save();
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
			]);
		}

		$input->trigger($version);

		if (!isset($version))
		{
			usleep(250 * 1000);
			Basic::$controller->redirect($input->short);
		}

		//BUG? this will return directly for non-new inputs
		$input->waitUntilNoLonger('busy');
		usleep(150 * 1000);

		$this->input = $input;
		$this->version = $version;
		$this->result = $this->input->getResult($version)->getSingle();

		$this->showTemplate('quick');
	}
}