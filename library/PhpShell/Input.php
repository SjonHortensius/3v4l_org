<?php

class PhpShell_Input extends PhpShell_Entity
{
	protected static $_relations = [
		'user' => PhpShell_User::class,
		'source' => PhpShell_Input::class,
		'runQuick' => PhpShell_Version::class,
	];
	protected static $_numerical = ['operationCount', 'run', 'penalty'];

	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	);
	const VLD_MATCH = '~ *(?<line>\d*) *\d+ *[ E]?[ >]+(?<op>[A-Z_]+) *(?<ext>[0-9A-F]*) *(?<return>[0-9:$]*)\s+(\'(?<operand>.*)\')?~';

	public function getCode()
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

	public static function clean($code)
	{
		return trim(str_replace(array("\r\n", "\r", "\xE2\x80\x8B"), array("\n", "\n", ""), $code));
	}

	public static function getHash($code)
	{
		return gmp_strval(gmp_init(sha1($code), 16), 58);
	}

	/** @return self */
	public static function byHash($hash)
	{
		return self::find('hash = ?', [$hash])->getSingle();
	}

	/** @return self */
	public static function create(array $data = [], bool $reload = true): Basic_Entity
	{
		if (false !== strpos($data['code'], 'pcntl_fork(') || false !== strpos($data['code'], ':|:&') || false !== strpos($data['code'], ':|: &'))
			throw new PhpShell_Input_GoFuckYourselfException('You must be really proud of yourself, trying to break a free service', [], 402);

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

		$input->trigger($input->runQuick);

		return $input;
	}

	public function updateOperations()
	{
		try
		{
			$vld = $this->getVld()->getSingle();
		}
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			return;
		}

		preg_match_all(self::VLD_MATCH, $vld->output->getRaw($this, 'vld'), $operations, PREG_SET_ORDER);
		$vld->output->removeCached();

		// Parse vld first, then update db accordingly
		$ops = [];
		foreach ($operations as $match)
		{
			$key = isset($match['operand']) ? $match['op'].':'.$match['operand'] : $match['op'];

			if (isset($ops[$key]))
				$ops[$key]++;
			else
				$ops[$key] = 1;
		}

		// Delete or update db by going through all existing rows
		foreach ($this->getRelated(PhpShell_Operation) as $op)
		{
			$key = isset($op->operand) ? $op->operation.':'.$op->operand : $op->operation;

			if (!isset($ops[$key]))
				$op->delete();
			elseif ($op->count != $ops[$key])
				$op->save(['count' => $ops[$key]]);

			unset($ops[$key]);
		}

		// Now create all missing operations
		foreach ($ops as $key => $count)
		{
			$operand = null;
			if (false == strpos($key, ':'))
				$operation = $key;
			else
				list($operation, $operand) = explode(':', $key, 2);

			try
			{
				PhpShell_Operation::create(['input' => $this, 'operation' => $operation, 'operand' => $operand, 'count' => $count]);
			}
			catch (PhpShell_Operation_InvalidDataException $e)
			{
					// ignore
			}
		}

		$this->save(['operationCount' => count($operations)]);
	}

	public function trigger(PhpShell_Version $version = null)
	{
		if (Basic::$database->query("SELECT COUNT(*) c FROM queue WHERE input = ?", [$this->short])->fetchArray('c')[0] > 0)
			return false;

		PhpShell_Submit::create(['input' => $this->id, 'ip' => $_SERVER['REMOTE_ADDR']]);
		Basic::$database->query("INSERT INTO queue VALUES (?, ?)", [$this->short, $version->name]);

		$this->waitUntilNoLonger('new');

		usleep(100 * 1000);
	}

	public function waitUntilNoLonger($state)
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
		$this->run = $input->run;
	}

	public function getRfcOutput()
	{
		$results = (new PhpShell_MainScriptOutput)->getSubset('result.input = ? AND result.run = ? AND version.name LIKE \'rfc%\'', [$this->id, $this->run]);

		return $results->setOrder(['version.released' => false]);
	}

	public function getOutput()
	{
		$results = (new PhpShell_MainScriptOutput)->getSubset('result.input = ? AND result.run = ? AND NOT version."isHelper"', [$this->id, $this->run]);
		$results->setOrder(['version.order' => true]);

		$abbrMax = function($name)
		{
			return str_replace(['hhvm-', 'php7@'], '', $name);
		};

		$outputs = array();
		foreach ($results as $result)
		{
			$output = $result->output->getRaw($result->input, $result->version->name);

			$hash = sha1($output.':'.$result->exitCode);
			$slot =& $outputs[ $hash ];

			$isHhvm = (false !== strpos($result->version->name, 'hhvm-'));

			$result->version->name = '<span title="released '. $result->version->released. '">'.$result->version->name.'</span>';

			if (!isset($slot)) #FIXME; use PhpShell_Output as $slot for getSubmitHash ?
				$slot = ['min' => $result->version->name, 'versions' => [], 'order' => 0, 'isAsserted' => $result->isAsserted];
			elseif ($hash != $prevHash || ($isHhvm && !$prevHhvm) || (!$isHhvm && $prevHhvm))
			{
				// Close previous slot
				if (isset($slot['max']))
					array_push($slot['versions'], $slot['min'] .' - '. $abbrMax($slot['max']));
				elseif (isset($slot['min']))
					array_push($slot['versions'], $slot['min']);

				$slot['min'] = $result->version->name;
				unset($slot['max']);
			}
			elseif (!isset($slot['min']))
				$slot['min'] = $result->version->name;
			else
				$slot['max'] = $result->version->name;

			$slot['order'] = max($slot['order'], $result->version->order);
			$slot['output'] = htmlspecialchars($output, ENT_SUBSTITUTE);

			if ($result->exitCode > 0)
			{
				$title = isset(self::$_exitCodes[ $result->exitCode ]) ? ' title="'. self::$_exitCodes[ $result->exitCode ] .'"' : '';
				$slot['output'] .= '<br/><i>Process exited with code <b'. $title .'>'. $result->exitCode .'</b>.</i>';
			}

			$prevHash = $hash;
			$prevHhvm = $isHhvm;
		}

		usort($outputs, function($a, $b){ return $b['order'] - $a['order']; });

		$versions = array();
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

	public function getPerf()
	{
		return Basic::$database->query("
			SELECT
				ROUND(AVG(\"systemTime\")::numeric, 3) as system,
				ROUND(AVG(\"userTime\")::numeric, 3) as user,
				ROUND(AVG(\"maxMemory\")/1024, 2) as memory,
				MAX(version.name) as version,
				SUM(result.\"exitCode\") as exit_sum
			FROM result
			INNER JOIN version ON version.id = result.version
			WHERE result.input = ? AND NOT version.\"isHelper\"
			GROUP BY result.version
			ORDER BY MAX(version.order) DESC", [$this->id]);
	}

	public function getRefs()
	{
		return Basic::$database->query("
			WITH RECURSIVE recRefs(id, operation, link, name, parent) AS (
				SELECT id, r.operation, link, name, parent
				FROM operations o
				INNER JOIN \"references\" r ON r.operation = o.operation AND (o.operand = r.operand OR r.operand IS NULL)
				WHERE input = ?
				UNION ALL
				SELECT C.id, C.operation, C.link, C.name, C.parent
				FROM recRefs P
				INNER JOIN \"references\" C on P.id = C.parent
			)
			SELECT link, name FROM recRefs ORDER BY name ASC;", [$this->id]);
	}

	public function getLastModified()
	{
		return Basic::$database->query("SELECT MAX(created) max FROM result WHERE input = ? AND run = ?", [$this->id, $this->run])->fetchArray('max')[0];
	}

	public function getResult(PhpShell_Version $version)
	{
		return $this->getRelated('PhpShell_Result')->getSubset("run = ? AND version = ?", [$this->run, $version]);
	}

	public function getSegfault()
	{
		$version = PhpShell_Version::byName('segfault');

		return $this->getResult($version)->getSubset('"exitCode" = 139');
	}

	public function getVld()
	{
		return $this->getResult(PhpShell_Version::byName('vld'));
	}

	public function getBytecode()
	{
		$version = PhpShell_Version::byName('hhvm-bytecode');

		return $this->getResult($version)->getSubset('"exitCode" = 0');
	}

	public function getCreatedUtc($format = 'Y-m-d\TH:i:s\Z')
	{
		$dt = new DateTime($this->created, new DateTimeZone('UTC'));
		return $dt->setTimezone(new DateTimeZone('UTC'))->format($format);
	}

	protected function _checkPermissions($action): bool
	{
		if ($action == 'save' && isset($this->_dbData->title) && $this->title !== $this->_dbData->title)
		{
			if (!isset($this->user) || $this->user->id !== Basic::$action->user->id)
				throw new PhpShell_Input_TitleChangeNotAllowedException('Permission denied, only the owner can update the title', [], 403);
		}

		return true;
	}
}