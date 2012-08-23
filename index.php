<?php

class PHPShell_Action
{
	const IN  = '/var/lxc/php_shell/in/';
	const OUT = '/var/lxc/php_shell/out/';
	protected static $_exitCodes = array(
		139 => 'Segmentation Fault',
		255 => 'Generic Error',
	);

	public function __construct()
	{
		$uri = substr($_SERVER['REQUEST_URI'], 1);
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$params = explode('/', $uri);

		if (false === $uri)
			$uri = 'home';

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
		self::_outputHeader($short);
?>
	<form method="POST" action="/new">
		<h1>3v4l.org<small> - online PHP shell, test in 70+ different PHP versions!</small></h1>
		<textarea name="code"><?=htmlspecialchars("<?php\n")?></textarea>
		<input type="submit" value="eval();" />
	</form>

	<h2>Examples:</h2>
	<ul>
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
			file_put_contents(self::IN.$short, $_POST['code']);
		else
		{
			if (self::_isBusy($short))
				return $this->getError(403);

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

		if (!preg_match('~^[a-z0-9]{5,}$~i', $short) || !file_exists(self::IN.$short))
			return $this->getError(404);

		$code = file_get_contents(self::IN.$short);
		$isBusy = self::_isBusy($short);

		self::_outputHeader($short);
?>
	<form method="POST" action="/new">
		<h1>3v4l.org<small> - online PHP shell, test in 70+ different PHP versions!</small></h1>
		<textarea name="code"><?=htmlspecialchars($code)?></textarea>
		<input type="submit" value="eval();"<?=($isBusy?' class="busy"' : '')?> />
	</form>
<?
		if ('output' == $type)
			$this->_getOutput($short);
		elseif ('vld' == $type)
			$this->_getVld($short);

		self::_outputFooter();
	}

	protected static function _outputHeader($title = '')
	{
?><!DOCTYPE html>
<html dir="ltr" lang="en-US">
<head>
	<title>3v4l.org - online PHP codepad for 70+ PHP versions<?=($title?" :: $title" : '')?></title>
	<meta name="keywords" content="php,codepad,fiddle,phpfiddle,shell"/>
	<link href="/favicon.ico" rel="shortcut icon" type="image/x-icon" />
	<link rel="stylesheet" href="/s/c.css?3"/>
</head>
<body>
<?
	}

	protected static function _outputFooter()
	{
?>
	<a href="https://twitter.com/3v4l_org" target="_blank">Follow us on Twitter</a>

	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js"></script>
	<script type="text/javascript" src="/s/c.js?3"></script>
</body>
</html><?
	}

	protected function _getOutput($file)
	{
		$results = array();
		$outputs = array();
		$files = glob(self::OUT. $file .'/*[0-9]');

		if (!$files)
			return;

		natsort($files);
		foreach (array_reverse($files, true) as $r)
		{
			$output = htmlspecialchars(ltrim(file_get_contents($r), "\n"));

			if (file_exists($r.'-exit'))
			{
				$code = file_get_contents($r.'-exit');
				$title = isset(self::$_exitCodes[$code]) ? ' title="'. self::$_exitCodes[$code] .'"' : '';
				$output .= '<br/><i>Process exited with code <b'. $title .'>'. $code .'</b>.</i>';
			}

			$h = crc32($output);
			$outputs[$h] = $output;
			$results[$h][] = basename($r);
		}

		echo '<dl>';

		foreach ($outputs as $hash => $output)
		{
			uksort($results[$hash], 'version_compare');

			echo '<dt id="v'.str_replace('.', '', end($results[$hash])).'">Output for ';
			echo implode(', ', self::groupVersions($results[$hash]));
			echo '</dt><dd>'. $output.'</dd>';
		}

		echo '</dl>';
	}

	protected function _getVld($short)
	{
		return var_dump('test');

		if (!file_exists(self::OUT. $file .'/vld'))
		{
			$path = self::IN.$short;
			echo '<pre>'.`php -dvld.active=1 -dvld.execute=0 $path` .'</pre>';
		}
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
