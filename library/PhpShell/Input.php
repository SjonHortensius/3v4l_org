<?php

class PhpShell_Input extends PhpShell_Entity
{
	protected static $_relations = [
		'user' => PhpShell_User,
		'source' => PhpShell_Input,
	];
	protected static $_numerical = ['operationCount', 'run', 'penalty'];

	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	);
	const PATH  = '/srv/http/3v4l.org/in/';
	const VLD_MATCH = '~ *(?<line>\d*) *\d+[ >]+(?<op>[A-Z_]+) *(?<ext>[0-9A-F]*) *(?<return>[0-9:$]*)\s+(\'(?<operand>.*)\')?~';

	public function getCode()
	{
		if ($this->state == 'private')
			throw new PhpShell_Input_PrivateException('This script is marked as private', [], 401);

		if (!is_readable(self::PATH. $this->short))
			throw new PhpShell_Input_NoSourceException('Although we have heard of this script; we are not sure where we left the sourcecode...', [], 404);

		return file_get_contents(self::PATH. $this->short);
	}

	public static function clean($code)
	{
		return trim(str_replace(array("\r\n", "\r", "\xE2\x80\x8B"), array("\n", "\n", ""), $code));
	}

	public static function getHash($code)
	{
		return gmp_strval(gmp_init(sha1($code), 16), 58);
	}

	public static function byHash($hash)
	{
		return self::find('hash = ?', [$hash])->getSingle();
	}

	public static function create($code, $extra = [])
	{
		if (false !== strpos($code, 'pcntl_fork(') || false !== strpos($code, ':|:&') || false !== strpos($code, ':|: &'))
			throw new PhpShell_Input_GoFuckYourselfException('You must be really proud of yourself, trying to break a free service');

		$hash = self::getHash($code);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$dups = self::find('short = ?', [$short]);

			$len++;
		}
		while (count($dups) > 0);

		if (file_exists(self::PATH. $short))
			throw new PhpShell_Input_DuplicateScriptException('Duplicate script, this shouldn\'t happen');

		umask(0022);
		file_put_contents(self::PATH. $short, $code);

		if (isset(Basic::$action->user))
			$extra['user'] = Basic::$action->user;

		$input = parent::create(['short' => $short, 'hash' => $hash] + $extra);
		$input->trigger();

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

		$this->save(['operationCount' => count($operations)]);

		foreach ($operations as $match)
		{
			PhpShell_Operation::create([
				'input' => $this,
				'operation' => $match['op'],
				'operand' => isset($match['operand']) ? $match['operand'] : null,
			]);
		}
	}

	public function trigger(PhpShell_Version $version = null)
	{
		// flush possible nginx fastcgi_cache, match levels=1:2, key="$request_method:$request_uri:$http_cookie";
		$h = md5("GET:/{$this->short}:{$_SERVER['HTTP_COOKIE']}");
		$cachePath = "/var/tmp/nginx/{$h[31]}/{$h[29]}{$h[30]}/$h";
		if (Basic::$config->PRODUCTION_MODE && is_writable($cachePath))
			unlink($cachePath);

		if (0 == Basic::$database->query("SELECT COUNT(*) c FROM queue WHERE input = ?", [$this->short])->fetchArray('c')[0])
		{
			// Calculate lowest version based on input.created [sexy time]
			if (!$this->runArchived)
				$version = Basic::$database->query("SELECT name FROM version WHERE released > ?::date - ?::interval ORDER BY released ASC LIMIT 1", [$this->created, '3 years'])->fetchArray('name')[0];

			Basic::$database->query("INSERT INTO queue VALUES (?, ?, ?)", [$this->short, isset($version) ? $version : null, (int)!$this->runArchived]);
		}

		$i = 0;
		do
		{
			usleep(100 * 1000);

			$input = PhpShell_Input::find("id = ?", [$this->id])->getSingle();
			foreach ($input as $k => $v)
				$this->$k = $v;
		}
		while (++$i < 15 && $this->state == 'new');

		usleep(100 * 1000);
	}

	public function getOutput()
	{
		$results = new PhpShell_MainScriptOutput(PhpShell_Result, 'input = ? AND result.run = ? AND NOT version."isHelper"', array($this->id, $this->run), ['version.order' => true]);

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
			$isNg = (false !== strpos($result->version->name, '@201'));

			$result->version->name = '<span title="released '. $result->version->released. '">'.$result->version->name.'</span>';

			if (!isset($slot))
				$slot = array('min' => $result->version->name, 'versions' => [], 'order' => 0);
			elseif ($hash != $prevHash || ($isNg && !$prevNg) || ($isHhvm && !$prevHhvm))
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
			$prevNg = $isNg;
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

			$versions[ implode(', ', $output['versions']) ] = $output['output'];
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
			SELECT link, name FROM recRefs;", [$this->id]);
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
		$version = PhpShell_Version::byName('vld');
		$emptyOutput = Basic::$cache->get(__CLASS__.'::vldEmpty', function(){
			return PhpShell_Output::find("hash = ?", [base64_encode(sha1('', true))])->getSingle();
		});

		return $this->getResult($version)->getSubset('output != ?', [$emptyOutput]);
	}

	public function getBytecode()
	{
		$version = PhpShell_Version::byName('hhvm-bytecode');

		return $this->getResult($version)->getSubset('"exitCode" = 0');
	}

	protected function _checkPermissions($action)
	{
		if ($action == 'save' && isset($this->_dbData->title) && $this->title !== $this->_dbData->title)
		{
			if (!isset($this->user) || $this->user->id !== Basic::$action->user->id)
				throw new PhpShell_Input_TitleChangeNotAllowedException('Permission denied, only the owner can update the title', [], 403);
		}

		return true;
	}

	public function getCreatedUtc($format = 'Y-m-d\TH:i:s\Z')
	{
		$dt = new DateTime($this->created);
		return $dt->setTimezone(new DateTimeZone('UTC'))->format($format);
	}
}