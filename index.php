<?php

require('/srv/http/.common/TooBasic/init.php');

class Phpshell_Controller extends TooBasic_Controller
{
	const IN  = '/var/lxc/php_shell/in/';
#	const IN  = '/var/backup/3v4l.org/var/lxc/php_shell/in/';
	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	);
	protected $_db;

	protected function _construct()
	{
		$this->_db = new PDO('pgsql:host=localhost;dbname=phpshell', 'website', '3lMC5jLazzzvd3K9lRyt0lVC5');
		$this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function getIndex()
	{
		TooBasic_Template::show('index');
	}
/*
	public function getBlog($id)
	{
		$id = (int)$id;

		TooBasic_Template::show('blog', ['content' => file_get_contents('b/'.$id.'.html')]);
	}

	public function getSearch($operation, $operand = null)
	{
		$results = $this->_query('');

		TooBasic_Template::show('search', ['results' => $results]);
	}
*/
	public function getUpdateOperations($waa)
	{
		ini_set('memory_limit', '128M');

		if ($waa != 'meukee')
			return $this->getError(404);

		foreach ($this->_query("SELECT short FROM input WHERE state != ? AND \"operationCount\" ISNULL", array('new')) as $r)
		{
			ob_start();
			$this->get($r->short, 'output');
			ob_end_clean();
			echo '.';
		}
	}

	public function getLast()
	{
		$entries = $this->_query('
			SELECT
				input,
				AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) "nrOutput",
				MAX(run) run
			FROM result
			WHERE input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 10)
			GROUP BY input'
		);

		TooBasic_Template::show('last', ['last' => $entries]);
	}

	public function postNew()
	{
		if (false === strpos($_POST['code'], '<?'))
			return $this->getError(400);

		$_POST['code'] = trim(str_replace(array("\r\n", "\r"), array("\n", "\n"), $_POST['code']));

		$hash = gmp_strval(gmp_init(sha1($_POST['code']), 16), 58);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$input = $this->_query("SELECT * FROM input WHERE short = ?", array($short));

			$len++;
		}
		while (!empty($input) && $hash !== $input[0]->hash);

		if (empty($input))
		{
			file_put_contents(self::IN.$short, $_POST['code']);

			$source = null;
			if (preg_match('~^https?://3v4l.org/([a-zA-Z0-9]{5,})[/#]?~', $_SERVER['HTTP_REFERER'], $matches))
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
#maintenance mode
#return $this->getError(501);
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

		$input = $this->_query("SELECT * FROM input WHERE short = ?", array($short));

		if (empty($input))
		{
			$input = $this->_query("SELECT * FROM input WHERE alias = ?", array($short));

			if (empty($input))
				return $this->getError(404);
			else
				die(header('Location: /'. $input[0]->short .($type != 'output'? '/'.$type : ''), 302));
		}
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

		if (!isset($input->operationCount) && $vld = $this->_getVld($input))
		{
			$count = preg_match_all('~ *(?<line>\d*) *\d+[ >]+(?<op>[A-Z_]+) *(?<ext>[0-9A-F]*) *(?<return>[0-9:$]*)\s+(\'(?<operand>.*)\')?~', $vld, $matches, PREG_SET_ORDER);
			$this->_query("UPDATE input SET \"operationCount\" = ? WHERE short = ?", array($count, $short));

			foreach ($matches as $match)
			{
				try
				{
					$this->_query("INSERT INTO operations VALUES(?, ?, ?)", array($short, $match['op'], isset($match['operand']) ? $match['operand'] : null));
				} catch (PDOException $e){}
			}
		}

		TooBasic_Template::show('get', [
			'title' =>  $short,
			'input' => $input,
			'code' => file_get_contents(self::IN. $short),
			'data' => call_user_func(array($this, '_get'. ucfirst($type)), $input),
			'tab' => $type,
			'showTab' => array(
				'perf' => true,
				'vld' => strlen($this->_getVld($input)) > 0,
				'refs' => count($this->_getRefs($input)) > 0,
				'segfault' => strlen($this->_getSegfault($input)) > 0,
			),
		]);
	}

	protected function _getOutput($input)
	{
		$results = $this->_query("
			SELECT version, \"exitCode\", raw
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
				$slot = array('min' => $result->version, 'versions' => array());
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

			$prevHash = $hash;

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

		end($versions);
		if ('hhvm-3.0.1' == key($versions))
		{
			$output = array_pop($versions);
			$versions = ['hhvm-3.0.1' => $output] + $versions;
		}

		return $versions;
	}

	protected function _getPerf(Stdclass $input)
	{
		return $this->_query("
			SELECT
				AVG(\"systemTime\") as system,
				AVG(\"userTime\") as user,
				AVG(\"maxMemory\") as memory,
				version
			FROM result
			INNER JOIN version ON version.name = result.version
			WHERE result.input = ? AND (version.name LIKE '_.%' OR version.name LIKE 'hhvm-%')
			GROUP BY result.version
			ORDER BY MAX(version.order)", array($input->short));
	}

	protected function _getVld(Stdclass $input)
	{
		return $this->__getResult($input, 'vld');
	}

	protected function _getHhvm(Stdclass $input)
	{
		return $this->__getResult($input, 'hhvm-3.0.1');
	}

	protected function _getSegfault(Stdclass $input)
	{
		return $this->__getResult($input, 'segfault');
	}

	protected function __getResult(Stdclass $input, $version)
	{
		$row = $this->_query("
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

	protected function _getRefs(Stdclass $input)
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
SELECT link, name FROM recRefs;", array($input->short));
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

	protected function _query($statement, array $parameters = array())
	{
		$s = $this->_db->prepare($statement);
		$s->execute($parameters);

		return $s->fetchAll(PDO::FETCH_CLASS);
	}
}

Phpshell_Controller::dispatch();