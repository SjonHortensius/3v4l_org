<?php

class PHPShell_Action
{
	const IN  = '/var/lxc/php_shell/in/';
	const OUT = '/var/lxc/php_shell/out/';
	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		255 => 'Generic Error',
	);
	protected $_db;

	public function __construct()
	{
		$uri = substr($_SERVER['REQUEST_URI'], 1);
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$params = explode('/', $uri);

		if (false === $uri)
			$uri = 'home';

		ignore_user_abort();
		$this->_db = new PDO('sqlite:db.sqlite');
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
		self::_outputHeader();
?>
	<form method="POST" action="/new">
		<h1>3v4l.org<small> - online PHP shell, test in 80+ different PHP versions!</small></h1>
		<textarea name="code"><?=htmlspecialchars("<?php\n")?></textarea>
		<input type="submit" value="eval();" />
	</form>

	<h2>Examples:</h2>
	<ul>
		<li><a href="/TpeZO">Booleans can be changed within a namespace</a>
		<li><a href="/XsL22">A resource which is cast to an object will result in a key 'scalar'</a></li>
		<li><a href="/11Ltt"> __toString evolves when used in comparisons</a></li>
		<li><a href="/ni9WO">New binary implementation and its problems</a></li>
		<li><a href="/2eNuB">Overwriting $this when using references</a></li>
		<li><a href="/1Z4W4">Broken formatting in DateTime</a></li>
	</ul>
<?
		self::_outputFooter();
	}

	public function postNew()
	{
		$hash = gmp_strval(gmp_init(sha1($_POST['code']), 16), 58);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$exists = file_exists(self::IN.$short);

			$len++;
		}
		while ($exists && sha1($_POST['code']) !== sha1_file(self::IN.$short));

		if (!$exists)
		{
			file_put_contents(self::IN.$short, $_POST['code']);
			$this->_query("INSERT INTO input(hash) VALUES (?)", array($short));
			$this->_query("INSERT INTO submit VALUES (?, ?, datetime(), null, 1)", array($short, $_SERVER['REMOTE_ADDR']));
		}
		else
		{
			if (self::_isBusy($short))
				return $this->getError(403);

			$this->_db->query("UPDATE submit SET updated = datetime(), count = count + 1 WHERE input = ? AND ip = ?", array($short, $_SERVER['REMOTE_ADDR']));

			touch(self::IN.$short);
			usleep(200000);
		}

		die(header('Location: /'. $short, 302));
	}

	public function getError($code = null)
	{
		switch ($code)
		{
			case 400:	$text = 'Bad request, you did not specify any code to process.'; break;
			case 402:	$text = 'This service is provided free of charge and we expect you not to abuse it.<br />Please contact us to get your IP unblocked.'; break;
			case 403:	$text = 'The server is already processing your code, please wait for it to finish.'; break;
			case 404:	$text = 'The requested script does not exist.'; break;
			case 405:	$text = 'Method not allowed.'; break;
			case 503:	$text = 'Please refrain from hammering this service. You are limited to 5 POST requests per minute.'; break;
			default:	$code = 500; $text = 'An unexpected error has occured.';
		}

		http_response_code($code);

		self::_outputHeader('Error '. $code);

?>
	<h1>3v4l.org<small> - Error <?=$code?></small></h1>
	<p><?=$text?></p>
<?

		self::_outputFooter();
	}

	public function get($short = '', $type = 'output')
	{
		// Bug? A hammering user will GET /new which doesn't exist and results in 404 instead of 503
		if ('new' == $short)
			return $this->getError(503);

		if (!preg_match('~^[a-z0-9]{5,}$~i', $short) || !file_exists(self::IN.$short) || !in_array($type, array('output', 'vld', 'perf')))
			return $this->getError(404);

		$code = file_get_contents(self::IN.$short);
		$isBusy = self::_isBusy($short);

		self::_outputHeader($short);
?>
	<form method="POST" action="/new">
		<h1>3v4l.org<small> - online PHP shell, test in 80+ different PHP versions!</small></h1>
		<textarea name="code"><?=htmlspecialchars($code)?></textarea>
		<input type="submit" value="eval();"<?=($isBusy?' class="busy"' : '')?> />
	</form>

	<ul id="tabs">
		<li<?= ('output' == $type ? ' class="active"' : '') ?>><a href="/<?=$short?>#tabs">Output</a></li>
		<li<?= ('vld' == $type ? ' class="active"' : '') ?>><a href="/<?=$short?>/vld#tabs">VLD opcodes</a></li>
	</ul>

	<div>
<?
		if ('output' == $type)
			$this->_getOutput($short);
		elseif ('vld' == $type)
			$this->_getVld($short);

?>
	</div>
<?

		self::_outputFooter();
	}

	protected static function _outputHeader($title = '')
	{
?><!DOCTYPE html>
<html dir="ltr" lang="en-US">
<head>
	<title>3v4l.org - online PHP codepad for 80+ PHP versions<?=($title?" :: $title" : '')?></title>
	<meta name="keywords" content="php,codepad,fiddle,phpfiddle,shell"/>
	<meta name="creator" content="Sjon Hortensius - sjon@hortensius.net" />
	<link href="/favicon.ico" rel="shortcut icon" type="image/x-icon" />
	<link rel="stylesheet" href="/s/c.css?5"/>
</head>
<body>
<?
	}

	protected static function _outputFooter()
	{
?>
	<a href="https://twitter.com/3v4l_org" target="_blank">Follow us on Twitter</a>

	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js"></script>
	<script type="text/javascript" src="/s/c.js?5"></script>
</body>
</html><?
	}

	protected function _getOutput($short)
	{
		$results = $this->_query("
			SELECT version, exitCode, hash, raw
			FROM result
			INNER JOIN output
				ON output.hash = result.output
			WHERE result.input = ? and version LIKE '_.%'
			ORDER BY hash, version", array($short));

		if (empty($results))
		{
			if ($this->_importOutput($short))
				return $this->_getOutput($short);

			echo '<dl></dl>';
			return;
		}

		$versionResults = array();
		$outputs = array();
		foreach ($results as $result)
		{
			$output = htmlspecialchars(ltrim($result['raw'], "\n"), ENT_SUBSTITUTE);

			if ($result['exitCode'] > 0)
			{
				$title = isset(self::$_exitCodes[ $result['exitCode'] ]) ? ' title="'. self::$_exitCodes[ $result['exitCode'] ] .'"' : '';
				$output .= '<br/><i>Process exited with code <b'. $title .'>'. $result['exitCode'] .'</b>.</i>';
			}

			$h = $result['hash'];
			$outputs[$h] = $result['raw'];
			$versionResults[$h][] = $result['version'];
		}

		echo '<dl>';
		foreach ($outputs as $hash => $output)
		{
			uksort($versionResults[$hash], 'version_compare');

			echo '<dt id="v'.str_replace('.', '', end($versionResults[$hash])).'">Output for ';
			echo implode(', ', self::groupVersions($versionResults[$hash]));
			echo '</dt><dd>'. $output.'</dd>';
		}
		echo '</dl>';
	}

	protected function _importOutput($short)
	{
		$files = glob(self::OUT. $short .'/*[0-9]');

		if (!$files)
			return false;

		foreach ($files as $r)
		{
			$content = file_get_contents($r);
			$this->_query("INSERT or IGNORE INTO output VALUES(?, ?)", array(md5($content), $content));

			list($tu, $ts) = explode(':', file_get_contents($out.'-timing'));
			$this->_query("INSERT INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?)", array(
				$short, $hash, basename($r), file_exists($r.'-exit') ? file_get_contents($r.'-exit') : 0,
				filemtime($r), $tu, $ts
			));
		}
	}

	protected function _query($statement, array $values)
	{
		$s = $this->_db->prepare($statement);
		foreach ($values as $idx => $value)
			$s->bindValue(is_int($idx) ? 1+$idx : $idx, $value);

		$s->execute();
		return $s->fetchAll(PDO::FETCH_ASSOC);
	}

	protected function _getVld($short)
	{
		$results = $this->_query("
			SELECT raw
			FROM result
			INNER JOIN output
				ON output.hash = result.output
			WHERE result.input = ? and version = 'vld'", array($short));

		if (empty($results))
			return print('No VLD output found, please wait for process to complete');

		echo '<pre>'. $results[0]['raw'] .'</pre><p>Generated using <a href="http://derickrethans.nl/projects.html#vld">Vulcan Logic Dumper</a>, using php 5.4.0</p>';
	}

	public static function groupVersions(array $versions)
	{
		usort($versions, 'version_compare');

		$ranges = array();

		// First filter the special cases
		foreach ($versions as $key => $version)
		{
			if (!preg_match('~^\d+\.\d+\.\d+$~', $version))
			{
				$ranges[] = $version;
				unset($versions[$key]);
				continue;
			}
		}

		$current_minor = $lowest_release = $previous_release = null;
		$versions[] = '999.999.999'; // Cheasy loop end (last item is ignored, but trigger writing last range)
		foreach ($versions as $version)
		{
			preg_match('~^(\d+\.\d+)\.(\d+)$~', $version, $matches);

			if (!isset($current_minor))
			{
				$current_minor = $matches[1];
				$lowest_release = $matches[2];
			}
			else if ($current_minor && ($current_minor != $matches[1] || $previous_release + 1 != $matches[2]))
			{
				if ($lowest_release == $previous_release)
					$ranges[] = $current_minor . '.' . $lowest_release;
				else
					$ranges[] = $current_minor . '.' . $lowest_release . ' - ' . $current_minor . '.'. $previous_release;

				$current_minor = $matches[1];
				$lowest_release = $matches[2];
			}

			$previous_release = $matches[2];
		}

		return $ranges;
	}

	protected static function _isBusy($short)
	{
		return (16749 != fileperms(self::OUT . $short));
	}
}

$action = new PHPShell_Action();