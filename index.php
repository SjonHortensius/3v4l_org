<?php

require('/srv/http/.common/TooBasic/init.php');

class Phpshell_Controller extends TooBasic_Controller
{
	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	);
	public static $db;

	protected function _construct()
	{
		self::$db = new TooBasic_Pdo('pgsql:host=localhost;dbname=phpshell', 'website', 'QVT359fHZXqobf2SV2MiZ9uR');
	}

	public function getIndex()
	{
		TooBasic_Template::show('index', [
			'code' => "<?php\n\n",
			'input' => (object)['source' => null, 'state' => 'new'],
		]);
	}

	public function getSearch($operation, $operand = null)
	{
		$entries = self::$db->fetchObjects('
			SELECT
				short as input,
				input."operationCount",
				input.run run,
				operations.operation
				AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance
			FROM input
			JOIN result ON (result.input = input.short)
			WHERE input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)
			GROUP BY input.short
		');

		TooBasic_Template::show('search', ['results' => $results]);
	}

	public function getLast()
	{
		$entries = self::$db->fetchObjects('
			SELECT
				short as input,
				input."operationCount",
				input.run run,
				AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance
			FROM input
			JOIN result ON (result.input = input.short)
			WHERE input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)
			GROUP BY input.short
		', []);

		TooBasic_Template::show('last', ['last' => $entries]);
	}

	public function postNew()
	{
		if (false === strpos($_POST['code'], '<?'))
			return $this->getError(400);

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

	public function getError($code = null)
	{
		switch ($code)
		{
			case 400:	$text = 'Bad request, you did not specify any code to process; or your input didn\'t contain any php code.'; break;
			case 402:	$text = 'This service is provided free of charge and we expect you not to abuse it.<br />Please contact us to get your IP unblocked.'; break;
			case 403:	$text = 'The server is already processing your code, please wait for it to finish.'; break;
			case 404:	$text = 'The requested script does not exist.'; break;
			case 405:	$text = 'Method not allowed.'; break;
			case 503:	$text = 'Please refrain from hammering this service. You are limited to 5 POST requests per minute.'; break;
			case 501:	$code = 503; $text = 'We are currently in maintenance, read-only mode.'; break;
			default:	$code = 500; $text = 'An unexpected error has occured.';
		}

		http_response_code($code);

		TooBasic_Template::show('error', [
			'title' =>  'Error '. $code,
			'code' => $code,
			'text' => $text,
		]);
	}

	public function get($short = '', $type = 'output')
	{
		// Bug? A hammering user will GET /new which doesn't exist and results in 404 instead of 503
		if ('new' == $short)
			return $this->getError(503);

		if (!method_exists($this, '_get'.ucfirst($type)))
			return $this->getError(404);

		try
		{
			$input = Phpshell_Script::byShort($short);
		}
		catch (Exception $e)
		{
			try
			{
				$input = self::$db->fetchObject("SELECT * FROM input WHERE alias = ?", [$short], 'Phpshell_Script');
				die(header('Location: /'. $input->short .($type != 'output' ? '/'.$type : ''), 302));
			}
			catch (Exception $e)
			{
				return $this->getError(404);
			}
		}

		// Attempt to retrigger the daemon
		if ($input->state == 'new')
		{
			$input->trigger();

			// Refresh state
			$input = Phpshell_Script::byShort($short);
		}

		if (!isset($input->operationCount) && $vld = $this->_getVld($input))
		{
			preg_match_all('~ *(?<line>\d*) *\d+[ >]+(?<op>[A-Z_]+) *(?<ext>[0-9A-F]*) *(?<return>[0-9:$]*)\s+(\'(?<operand>.*)\')?~', $vld, $matches, PREG_SET_ORDER);
			$input->setOperations($matches);
		}

		TooBasic_Template::show('script', [
			'title' =>  $input->short,
			'input' => $input,
			'code' => $input->getCode(),
			'data' => call_user_func(array($this, '_get'. ucfirst($type)), $input),
			'tab' => $type,
			'showTab' => [
				'perf' => true,
				'vld' => isset($input->operationCount),
				'refs' => count($this->_getRefs($input)) > 0,
				'segfault' => strlen($this->_getSegfault($input)) > 0,
				'analyze' => !in_array($this->_getAnalyze($input), [null, '[]']),
			],
		]);
	}

	public function postAssert($input, $version, $run)
	{
		$result = self::$db->fetchObject("SELECT * FROM result INNER JOIN version ON version.name = result.version WHERE input = ? AND version = ? AND !version.\"isHelper\"", [$input, $version]);



		self::$db->preparedExec("INSERT INTO assertion VALUES(?, ?, ?, ?)", [$input, $result->output, $result->exitCode, $user]);
	}

	protected function _getOutput(Phpshell_Script $input)
	{
		$results = self::$db->fetchObjects("
			SELECT version, \"exitCode\", raw, version.order
			FROM result
			INNER JOIN output ON output.hash = result.output
			INNER JOIN version ON version.name = result.version
			WHERE result.input = ? AND result.run = ? AND NOT version.order ISNULL
			ORDER BY version.order", array($input->short, $input->run));

		if (empty($results))
			return array();

		$outputs = array();
		foreach ($results as $result)
		{
			$output = ltrim(stream_get_contents($result->raw), "\n");
			$output = preg_replace('~(?<![\\\])\006~', $result->version, $output);
			$output = preg_replace('~(?<![\\\])\007~', $input->short, $output);
			$output = str_replace(array('\\'.chr(6), '\\'.chr(7)), array(chr(6), chr(7)), $output);
			$hash = sha1($output);

			$slot =& $outputs[ $hash ];

			if (!isset($slot))
				$slot = array('min' => $result->version, 'versions' => array(), 'order' => 0);
			elseif ($hash != $prevHash || false !== strpos($result->version, '-') || false !== strpos($result->version, '@'))
			{
				// Close previous slot
				if (isset($slot['max']))
					array_push($slot['versions'], $slot['min'] .' - '. $slot['max']);
				elseif (isset($slot['min']))
					array_push($slot['versions'], $slot['min']);

				$slot['min'] = $result->version;
				unset($slot['max']);
			}
			elseif (!isset($slot['min']))
				$slot['min'] = $result->version;
			else
				$slot['max'] = $result->version;

			$slot['order'] = max($slot['order'], $result->order);

			$prevHash = $hash;

			$slot['output'] = htmlspecialchars($output, ENT_SUBSTITUTE);

			if ($result->exitCode > 0)
			{
				$title = isset(self::$_exitCodes[ $result->exitCode ]) ? ' title="'. self::$_exitCodes[ $result->exitCode ] .'"' : '';
				$slot['output'] .= '<br/><i>Process exited with code <b'. $title .'>'. $result->exitCode .'</b>.</i>';
			}
		}

		usort($outputs, function($a, $b)
		{
			return $b['order'] - $a['order'];
		});

		$versions = array();
		foreach ($outputs as $output)
		{
			// Process unclosed slots
			if (isset($output['max']))
				array_push($output['versions'], $output['min'] .' - '. $output['max']);
			elseif (isset($output['min']))
				array_push($output['versions'], $output['min']);

			$versions[ implode(', ', $output['versions']) ] = $output['output'];
		}

		return $versions;
	}

	protected function _getPerf(Phpshell_Script $input)
	{
		return self::$db->fetchObjects("
			SELECT
				AVG(\"systemTime\") as system,
				AVG(\"userTime\") as user,
				AVG(\"maxMemory\") as memory,
				version
			FROM result
			INNER JOIN version ON version.name = result.version
			WHERE result.input = ? AND (version.name LIKE '_.%' OR version.name LIKE 'hhvm-_.%')
			GROUP BY result.version
			ORDER BY MAX(version.order)", array($input->short));
	}

	protected function _getVld(Phpshell_Script $input)
	{
		return $this->__getResult($input, 'vld');
	}

	protected function _getAnalyze(Phpshell_Script $input)
	{
		return $this->__getResult($input, 'hhvm-analyze');
	}

	// Deprecated
	protected function _getHhvm(Phpshell_Script $input)
	{
		die(header('Location: /'. $input->short, 302));
	}

	protected function _getSegfault(Phpshell_Script $input)
	{
		return $this->__getResult($input, 'segfault');
	}

	protected function __getResult(Phpshell_Script $input, $version)
	{
		$row = self::$db->fetchObjects("
			SELECT raw
			FROM result
			INNER JOIN output ON output.hash = result.output
			WHERE result.input = ? AND result.run = ? AND version = ?", array($input->short, $input->run, $version));

		if (empty($row))
			return null;

		$output = preg_replace('~(?<![\\\])\007~', $input->short, stream_get_contents($row[0]->raw));
		$output = str_replace('\\'.chr(7), chr(7), $output);

		return $output;
	}

	protected function _getRefs(Phpshell_Script $input)
	{
		return self::$db->fetchObjects("
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
SELECT link, name FROM recRefs;", array($input->short));
	}

	protected function _getRel(Phpshell_Script $input)
	{
		return self::$db->fetchObjects("SELECT * FROM input WHERE source = ? LIMIT 9", [$input->short], 'Phpshell_Script');
	}
}

class Phpshell_Script
{
	const PATH  = '/var/lxc/php_shell/in/';

	protected function __construct()
	{
		if (!isset($this->short))
			throw new Exception;
	}

	public static function clean($code)
	{
		return trim(str_replace(array("\r\n", "\r"), array("\n", "\n"), $code));
	}

	public static function getHash($code)
	{
		return gmp_strval(gmp_init(sha1($code), 16), 58);
	}

	public static function byHash($hash)
	{
		return Phpshell_Controller::$db->fetchObject("SELECT * from input WHERE hash = ?", [$hash], get_class());
	}

	public static function create($code, $source)
	{
		$hash = self::getHash($code);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$dups = Phpshell_Controller::$db->fetchObject("SELECT COUNT(*) FROM input WHERE short = ?", [$short]);

			$len++;
		}
		while ($dups->count > 0);

		if (file_exists(self::PATH. $short))
			throw new Exception('Duplicate script, this shouldn\'t happen');

		file_put_contents(self::PATH. $short, $code);

		Phpshell_Controller::$db->preparedExec("INSERT INTO input (short, source, hash) VALUES (?, ?, ?)", [$short, $source, $hash]);

		return self::byHash($hash);
	}

	public static function byShort($short)
	{
		return Phpshell_Controller::$db->fetchObject("SELECT * FROM input WHERE short = ?", [$short], get_class());
	}

	public function getCode()
	{
		if (!isset($this->short))
			throw new Exception('We don\'t really know this script...');

		if (!is_readable(self::PATH. $this->short))
			throw new Exception('Although we have heard of this script; we are not sure where we left the sourcecode...');

		return file_get_contents(self::PATH. $this->short);
	}

	public function setOperations(array $operations)
	{
		$this->operationCount = count($operations);
		Phpshell_Controller::$db->preparedExec("UPDATE input SET \"operationCount\" = ? WHERE short = ?", [$this->operationCount, $this->short]);

		foreach ($operations as $match)
		{
			try
			{
				if (isset($match['operand']) && strlen($match['operand']) > 64)
					continue;

				Phpshell_Controller::$db->preparedExec("INSERT INTO operations VALUES(?, ?, ?)", array($this->short, $match['op'], isset($match['operand']) ? $match['operand'] : null));
			} catch (PDOException $e){ }
		}
	}

	public function trigger()
	{
		touch(self::PATH. $this->short);
		usleep(200 * 1000);
	}
}

Phpshell_Controller::dispatch();