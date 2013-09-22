<?php

class PHPShell_Action
{
	const IN  = '/var/lxc/php_shell/in/';
	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	);
	protected $_db;

	public static function dispatch()
	{
		if (false !== strpos($_SERVER['REQUEST_URI'], '?'))
			$_SERVER['REQUEST_URI'] = strstr($_SERVER['REQUEST_URI'], '?', true);

		$uri = substr($_SERVER['REQUEST_URI'], 1);
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$params = explode('/', $uri);

		if (false === $uri)
			$uri = 'home';

		ignore_user_abort();
		new self($method, $uri, $params);
	}

	private function __construct($method, $uri, $params)
	{
		$this->_db = new PDO('pgsql:host=localhost;dbname=phpshell', 'website', '3lMC5jLazzzvd3K9lRyt0lVC5');
		$this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if (method_exists($this, $method . ucfirst($uri)))
			return call_user_func_array(array($this, $method . ucfirst($uri)), $params);

		return call_user_func_array(array($this, $method), $params);
	}

	public function __call($method, array $arguments)
	{
		$this->getError(405);
	}

	public function getHome()
	{
		PHPShell_Template::show('home');
	}

	public function getLast()
	{
		PHPShell_Template::show('last');
	}

	public function postNew()
	{
		if (false === strpos($_POST['code'], '<?'))
			return $this->getError(400);

		$hash = gmp_strval(gmp_init(sha1($_POST['code']), 16), 58);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$input = $this->_query("SELECT * FROM input WHERE short = ?", array($short));

			$len++;
		}
		while (!empty($input) && $hash !== $input[0]->hash);

		// After hashing so we dont get loads of new shorts from 'old' code
		$_POST['code'] = trim(str_replace(array("\r\n", "\r"), "\n", $_POST['code']));

		if (empty($input))
		{
			file_put_contents(self::IN.$short, $_POST['code']);

			$source = null;
			if (preg_match('~^http://3v4l.org/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $matches))
				$source = $matches[1];

			$this->_query("INSERT INTO input  VALUES (?, ?, null, ?, 'new')", array($short, $source, $hash));
			$this->_query("INSERT INTO submit VALUES (?, ?, now(), null, 1)", array($short, $_SERVER['REMOTE_ADDR']));
		}
		else
		{
			if ($input[0]->state == "busy")
				return $this->getError(403);

			try
			{
				$this->_query("INSERT INTO submit VALUES (?, ?, now(), null, 1)", array($short, $_SERVER['REMOTE_ADDR']));
			}
			catch (PDOException $e)
			{
				$this->_query("UPDATE submit SET updated = now(), count = count + 1 WHERE input = ? AND ip = ?", array($short, $_SERVER['REMOTE_ADDR']));
			}

			// Trigger daemon
			touch(self::IN. $short);
		}

		usleep(300000);
		die(header('Location: /'. $short, 302));
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
			default:	$code = 500; $text = 'An unexpected error has occured.';
		}

		http_response_code($code);

		PHPShell_Template::show('error', [
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

		$input = $this->_query("SELECT * FROM input WHERE short = ?", array($short));

		if (empty($input) || !method_exists($this, '_get'.ucfirst($type)))
			return $this->getError(404);
		else
			$input = $input[0];

		// Attempt to retrigger the daemon
		if ($input->state == 'new')
		{
			touch(self::IN. $short);
			usleep(200000);
			$input = $this->_query("SELECT * FROM input WHERE short = ?", array($short));
			$input = $input[0];
		}

		if (!isset($input->operationCount) && $vld = $this->_getVld($short))
		{
			$count = preg_match_all('~ *(?<line>\d*) *\d+[ >]*(?<op>[A-Z_]+) *(?<ext>\d*) *(?<return>[0-9:$]*)\s+(\'(?<operand>.*)\')?~', $vld, $matches, PREG_SET_ORDER);
			$this->_query("UPDATE input SET \"operationCount\" = ? WHERE short = ?", array($count, $short));

			foreach ($matches as $match)
			{
				try
				{
					$this->_query("INSERT INTO operations VALUES(?, ?, ?)", array($short, $match['op'], isset($match['operand']) ? $match['operand'] : null));
				} catch (PDOException $e){}
			}
		}

		PHPShell_Template::show('get', [
			'title' =>  $short,
			'input' => $input,
			'code' => file_get_contents(self::IN. $short),
			'data' => call_user_func(array($this, '_get'. ucfirst($type)), $short),
			'tab' => $type,
			'showTab' => array(
				'vld' => strlen($this->_getVld($short)) > 0,
				'refs' => count($this->_getRefs($short)) > 0,
				'segfault' => strlen($this->_getSegfault($short)) > 0,
			),
		]);
	}

	protected function _getOutput($short)
	{
		$results = $this->_query("
			SELECT version, \"exitCode\", output.hash ||':'|| \"exitCode\" as hash, raw
			FROM result
			INNER JOIN output ON output.hash = result.output
			INNER JOIN input ON input.short = result.input
			INNER JOIN version ON version.name = result.version
			WHERE result.input = ? AND result.run = input.run AND version LIKE '_.%'
			ORDER BY version.order", array($short));

		if (empty($results))
			return array();

		$outputs = array();
		foreach ($results as $result)
		{
			$slot =& $outputs[ $result->hash ];

			if (!isset($slot))
				$slot = array('min' => $result->version, 'versions' => array());
			elseif ($result->hash != $prevHash)
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

			$prevHash = $result->hash;

			// Replace all unescaped bell-chars by script path, and unescape raw bells
			$output = preg_replace('~(?<![\\\])\007~', '/in/'.$short, ltrim(stream_get_contents($result->raw), "\n"));
			$output = str_replace('\\'.chr(7), chr(7), $output);
			$slot['output'] = htmlspecialchars($output, ENT_SUBSTITUTE);

			if ($result->exitCode > 0)
			{
				$title = isset(self::$_exitCodes[ $result->exitCode ]) ? ' title="'. self::$_exitCodes[ $result->exitCode ] .'"' : '';
				$slot['output'] .= '<br/><i>Process exited with code <b'. $title .'>'. $result->exitCode .'</b>.</i>';
			}
		}

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

		uksort($versions, 'version_compare');
		$versions = array_reverse($versions, true);

		return $versions;
	}

	protected function _getPerf($short)
	{
		return $this->_query("
			SELECT
				AVG(\"systemTime\") as system,
				AVG(\"userTime\") as user,
				AVG(\"maxMemory\") as memory,
				version
			FROM result
			INNER JOIN version ON version.name = result.version
			WHERE result.input = ? AND version LIKE '_.%'
			GROUP BY result.version
			ORDER BY MAX(version.order)", array($short));
	}

	protected function _getVld($short)
	{
		return $this->__getResult($short, 'vld');
	}

	protected function _getSegfault($short)
	{
		return $this->__getResult($short, 'segfault');
	}

	protected function __getResult($short, $version)
	{
		$row = $this->_query("
			SELECT raw
			FROM result
			INNER JOIN input ON input.short = result.input
			INNER JOIN output ON output.hash = result.output
			WHERE result.input = ? AND result.run = input.run AND version = ?", array($short, $version));

		if (empty($row))
			return null;

		$output = preg_replace('~(?<![\\\])\007~', '/in/'.$short, stream_get_contents($row[0]->raw));
		$output = str_replace('\\'.chr(7), chr(7), $output);

		return $output;
	}

	protected function _getRefs($short)
	{
		return $this->_query("
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
SELECT link, name FROM recRefs;", array($short));
	}

/*
	protected function _getRel($short)
	{
		$related = $this->_query("SELECT short, \"operationCount\" FROM input WHERE source = ? LIMIT 9", array($short));

		foreach ($related as &$input)
			$input->perf = $this->_getPerf($input->short);

		return $related;
	}
*/

	protected function _query($statement, array $parameters)
	{
		$s = $this->_db->prepare($statement);
		$s->execute($parameters);

		return $s->fetchAll(PDO::FETCH_CLASS);
	}
}

class PHPShell_Template
{
	protected $_file;

	public function __construct($file)
	{
		$this->_file = $file;
	}

	public function getWrapped()
	{
		$wrapped = $this->get("_wrapper");
		$wrapped->content = $this;
		return $wrapped;
	}

	public function get($file)
	{
		$tpl = clone $this;
		$tpl->_file = $file;

		return $tpl;
	}

	public static function show($file, array $variables = array())
	{
		$tpl = new self($file);

		foreach ($variables as $key => $value)
			$tpl->$key = $value;

		print $tpl->getWrapped();
	}

	public function __toString()
	{
		ob_start();

		require('tpl/'. $this->_file .'.html');

		return ob_get_clean();
	}
}

PHPShell_Action::dispatch();