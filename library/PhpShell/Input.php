<?php

class PhpShell_Input extends PhpShell_Entity
{
    private const RFC_VERSION_TRESHOLD = 32;

	protected static $_relations = [
		'user' => PhpShell_User::class,
		'source' => PhpShell_Input::class,
	];
	protected static $_numerical = ['operationCount', 'penalty'];

	const VLD_MATCH = '~[ 0-9E>]+(?<op>[A-Z_]+) +(?<ext>[0-9A-F]*) +(?<return>[0-9:$]*) +(\'?)(?<operand>.*)\4\n~';
	// SELECT COUNT(*), function FROM "functionCall" WHERE input IN( SELECT input FROM (SELECT input, COUNT(output) c, COUNT(distinct output) u FROM result WHERE version>32 GROUP BY input) x WHERE c=u) GROUP BY function ORDER BY count DESC LIMIT 99;
	const BUGHUNT_BLACKLIST = ['lcg_value', 'rand', 'mt_rand', 'time', 'microtime', 'array_rand', 'disk_free_space', 'memory_get_usage', 'shuffle', 'timezone_version_get', 'random_int', 'uniqid', 'openssl_random_pseudo_bytes', 'phpversion', 'str_shuffle', 'random_bytes', 'str_shuffle', 'password_hash', 'time_nanosleep', 'gettimeofday', 'getrusage'];

	public function getCode(): string
	{
		if ($this->state == 'private')
			throw new PhpShell_Input_PrivateException('This script is marked as private', [], 401);

		try
		{
			return $this->getRelated(PhpShell_InputSource::class)->getSingle()->getRaw();
		}
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			throw new PhpShell_Input_NoSourceException('Although we have heard of this script; we are not sure where we left the sourcecode...', [], 404, $e);
		}
	}

	public static function clean(string $code): string
	{
		$code = trim(str_replace(["\r\n", "\r", "\xE2\x80\x8B"], ["\n", "\n", ""], $code));

		# add trailing newline for heredoc. If done globally, all script-hashes would change
		if (preg_match('~[0-9a-zA-Z_];$~', $code))
			$code .= "\n";

		return $code;
	}

	public static function getHash(string $code): string
	{
		return gmp_strval(gmp_init(sha1($code), 16), 58);
	}

	/** @return self */
	public static function byHash(string $hash): Basic_Entity
	{
		return self::find('hash = ?', [$hash])->getSingle();
	}

	/** @return self */
	public static function create(array $data = [], bool $reload = false): Basic_Entity
	{
		$hash = self::getHash($data['code']);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$dups = self::find('short = ?', [$short]);

			$len++;
		}
		while (count($dups) > 0);

		if (isset(Basic::$action->user))
			$data['user'] = Basic::$action->user;

		Basic::$database->beginTransaction();
		{
			$code = $data['code']; unset($data['code']);
			$input = parent::create(['short' => $short, 'hash' => $hash] + $data);
			PhpShell_InputSource::create(['input' => $input, 'raw' => $code]);
		}
		Basic::$database->commit();

		return $input;
	}

	protected function _updateFunctionCalls(string $vld)
	{
		preg_match_all(self::VLD_MATCH, $vld, $operations, PREG_SET_ORDER);

		// Parse vld first, then update db accordingly
		$calls = [];
		foreach ($operations as $match)
		{
			if (!isset($match['operand']))
				continue;

			if ($match['op'] == 'INIT_FCALL')
				$function = $match['operand'];
			// we threat all calls within a namespace as global for better matching
			elseif ($match['op'] == 'INIT_NS_FCALL_BY_NAME')
				$function = substr($match['operand'], 3+strrpos($match['operand'], '%5C'));
			else
				continue;

			if (isset($calls[ $function ]))
				continue;

			// Only store valid functionCalls, nothing from userspace
			try
			{
				$f = PhpShell_Function::find("text = ?", [$function])->getSingle();
			}
			catch (Basic_EntitySet_NoSingleResultException $e)
			{
				continue;
			}

			$calls[$function] = $f;

			if (in_array($function, PhpShell_Input::BUGHUNT_BLACKLIST))
				$this->bughuntIgnore = true;
		}

		// Delete or update db by going through all existing rows
		foreach ($this->getRelated(PhpShell_FunctionCall::class) as $c)
		{
			if (!isset($calls[ $c->function->text ]))
				$c->delete();

			unset($calls[ $c->function->text ]);
		}

		// Now create all missing entities
		foreach ($calls as $function => $f)
			PhpShell_FunctionCall::create(['input' => $this, 'function' => $f], false);

		$this->save(['operationCount' => count($operations), 'bughuntIgnore' => $this->bughuntIgnore]);
	}

	public function trigger(PhpShell_Version $version = null): void
	{
		if (count(PhpShell_QueuedInput::find("input = ?", [$this->short])) > 0)
			return;

		//FIXME this is locked because it is linked in /about
		if ('MtDjZ' == $this->short)
			return;

		PhpShell_Submit::create(['input' => $this->id, 'ip' => $_SERVER['REMOTE_ADDR'], 'isQuick' => isset($version)]);
		Basic::$database->q("INSERT INTO queue VALUES (?, ?)", [$this->short, $version?->name]);

		$this->waitUntilNoLonger('new');

		usleep(100 * 1000);
	}

	// intended for 'internal' triggers, such as helpers that produce large output we don't want to store
	protected function _triggerSilent(PhpShell_Version $version = null): void
	{
		Basic::$database->q("INSERT INTO queue VALUES (?, ?)", [$this->short, $version?->name]);
		$this->waitUntilNoLonger('busy');
	}

	public function waitUntilNoLonger($state): void
	{
		$i = 0;
		do
		{
			usleep(100 * 1000);

			$this->removeCached();
			$input = PhpShell_Input::get($this->id);
		}
		while (++$i < 15 && $input->state == $state);

		$this->state = $input->state;
	}

	public function getOutput(bool $forRfc = false): array
	{
		$results = $this->getRelated(PhpShell_Result::class)
			->addJoin(PhpShell_Version::class, "version.id = result.version")
			->getSubset("NOT version.\"isHelper\"")
			->addJoin(PhpShell_Output::class, "output.id = result.output")
			->setOrder(['version.order' => true]);

		if ($forRfc)
			$results = $results->getSubset("version < " . self::RFC_VERSION_TRESHOLD)->setOrder(['version.released' => false]);
		else
			$results = $results->getSubset("version >= " . self::RFC_VERSION_TRESHOLD);

		$abbrMax = function($name)
		{
			// strip repeating version in max for non-releases, but keep html intact
			if (preg_match('~^(.*>)(.*)(alpha|beta|rc)(.*)(<.*)$~', $name, $m))
				return $m[1] . $m[3] . $m[4] . $m[5];
			else
				return $name;
		};

		$outputs = [];
		/* @var PhpShell_Result $result */
		foreach ($results as $result)
		{
			$html = $result->getHtml();

			$hash = sha1($html);
			$slot =& $outputs[ $hash ];

			$major = substr($result->version->name, 0, 3);
			$name = '<span title="released '. $result->version->released. '">'.$result->version->name.'</span>';

			if (!isset($slot))
				$slot = ['min' => $name, 'versions' => [], 'order' => 0, 'isAsserted' => $result->isAsserted];
			elseif ($hash != $prevHash || $major !== $prevMajor || $forRfc)
			{
				// Close previous slot
				if (isset($slot['max']))
					$slot['versions'][] = $slot['min'] . ' - ' . $abbrMax($slot['max']);
				elseif (isset($slot['min']))
					$slot['versions'][] = $slot['min'];

				$slot['min'] = $name;
				unset($slot['max']);
			}
			else
				$slot['max'] = $name;

			$slot['order'] = max($slot['order'], $result->version->order);
			$slot['output'] = $html;

			$prevHash = $hash;
			$prevMajor = $major;
		}

		usort($outputs, function($a, $b){ return $b['order'] - $a['order']; });

		$versions = [];
		foreach ($outputs as $output)
		{
			// Process unclosed slots
			if (isset($output['max']))
				array_push($output['versions'], $output['min'] .' - '. $abbrMax($output['max']));
			elseif (isset($output['min']))
				array_push($output['versions'], $output['min']);

			$versions[ implode(', ', $output['versions']) ] = $output;
		}

		return $versions;
	}

	public function logHit(): void
	{
		if ($this->short == 'kuLmD')
			return;

		$hits = Basic::$cache->increment('Hits:'. $this->short .':'. (date('w')), 1, 1, 3*24*60*60) +
				Basic::$cache->get('Hits:'. $this->short .':'.((date('w')+5)%6), function(){ return 0; });

		$popular = Basic::$cache->get('Hits:popular', function(){ return []; });

		if (count($popular) >= PhpShell_Action_Index::ACTIVE_SCRIPTS)
		{
			$minimum = end($popular);

			if ($hits <= $minimum)
				return;
		}

		$popular[ $this->short ] = $hits;
		arsort($popular);
		$popular = array_slice($popular, 0, PhpShell_Action_Index::ACTIVE_SCRIPTS, true);

		Basic::$cache->set('Hits:popular', $popular);
	}

	public function getPerf(): Basic_DatabaseQuery
	{
		return Basic::$database->q("
			SELECT
				ROUND(AVG(\"systemTime\")::numeric, 3) as system,
				ROUND(AVG(\"userTime\")::numeric, 3) as user,
				ROUND(AVG(\"maxMemory\")/1024, 2) as memory,
				MAX(version.name) as version,
				SUM(\"exitCode\") as exit_sum
			FROM result
			INNER JOIN version ON version.id = version
			WHERE input = ? AND version.id >= ?
			GROUP BY version
			ORDER BY MAX(version.order) DESC", [$this->id, self::RFC_VERSION_TRESHOLD]);
	}

	public function getFunctionCalls(): Basic_EntitySet
	{
		return $this->getRelated(PhpShell_FunctionCall::class)
			->addJoin(PhpShell_Function::class, "function.id = \"functionCall\".function");
	}

	public function getLastModified(): string
	{
		return $this->lastResultChange ?? $this->created;
	}

	public function getResult(PhpShell_Version $version): Basic_EntitySet
	{
		return $this->getRelated(PhpShell_Result::class)
			->getSubset("version = ?", [$version]);
	}

	// This is a special case, vld output is not stored so we trigger the run, return the result and delete it immediately
	public function getVld(): string
	{
		$this->_triggerSilent(PhpShell_Version::byName('vld'));
		$result = $this->getResult(PhpShell_Version::byName('vld'))->getSingle();
		$output = $result->output->getRaw($this, $result->version);
		$this->_updateFunctionCalls($output);
		$result->delete();

		return $output;
	}

	public function getCreatedUtc($format = 'Y-m-d\TH:i:s\Z'): string
	{
		$dt = new DateTime($this->created, new DateTimeZone('UTC'));
		return $dt->setTimezone(new DateTimeZone('UTC'))->format($format);
	}

	protected function _checkPermissions(string $action): void
	{
		if (!($action == 'save' && isset($this->_dbData->title) && $this->title !== $this->_dbData->title))
			return;

		if (!isset($this->user) || $this->user->id !== Basic::$action->user->id)
			throw new PhpShell_Input_TitleChangeNotAllowedException('Permission denied, only the owner can update the title', [], 403);
	}
}
