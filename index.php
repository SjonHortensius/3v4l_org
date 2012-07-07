<?php

class PHPShell_Action
{
	const IN  = '/var/lxc/php_shell/in/';
	const OUT = '/var/lxc/php_shell/out/';

	public function __construct()
	{
		$action = substr($_SERVER['REQUEST_URI'], 1);
		$requestMethod = strtolower($_SERVER['REQUEST_METHOD']);
		$method = $requestMethod . ucfirst($action);

		if (method_exists($this, $method))
			return $this->$method();

		return $this->$requestMethod($action);
	}

	public function __call($method, array $arguments)
	{
		$this->getError(405);
	}

	public function postNew()
	{
		if (!isset($_POST['code']))
			return $this->getError(400);

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
		}

		die(header('Location: /'. $short, 302));
	}

	public function getError($code = null)
	{
		if (!isset($code) && isset($_GET['code']))
			$code = (int)$_GET['code'];

		if (isset($code))
			http_response_code($code);

		switch ($code)
		{
			case 400:	$text = 'Bad request, you did not specify any code to process.'; break;
			case 403:	$text = 'The server is already processing your code, please wait for it to finish.'; break;
			case 404:	$text = 'The requested script does not exist.'; break;
			case 405:	$text = 'Method not allowed.'; break;
			case 503:	$text = 'Please refrain from hammering this service. You are limited to 5 POST requests per minute.'; break;
			default:	$text = 'An unexpected error has occured.'; break;
		}

		self::_outputHeader($short);

?>
	<div>
		<h1>Error <?=$code?></h1>
		<p><?=$text?></p>
	</div>
<?

		self::_outputFooter();
	}

	public function get($short = '')
	{
		$short = substr($_SERVER['REQUEST_URI'], 1);
		$code = "<?php\n";

		if (strlen($short) > 3)
		{
			if (!preg_match('~^[a-z0-9]+$~i', $short) || !file_exists(self::IN.$short))
				return $this->getError(404);

			$code = file_get_contents(self::IN.$short);
			$isBusy = self::_isBusy($short);
		}

		self::_outputHeader($short);
?>
	<form method="POST" action="/new">
		<h1>3v4l.org<small> - online PHP shell, test in 70+ different PHP versions!</small></h1>
		<textarea name="code"><?=htmlspecialchars($code)?></textarea>
		<input type="submit" value="eval();"<?=($isBusy?' class="busy"' : '')?> />
	</form>
<?
		if ($short)
			$this->_getOutput($short);

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
		$files = glob(self::OUT.$file .'/*[0-9]');

		if (!$files)
			return;

		natsort($files);
		foreach (array_reverse($files, true) as $r)
		{
			$output = htmlspecialchars(ltrim(file_get_contents($r), "\n"));

			if (file_exists($r.'-exit'))
				$output .= '<br/><i>Process exited with code <b>'. file_get_contents($r.'-exit').'</b>.</i>';

			$h = crc32($output);
			$outputs[$h] = $output;
			$results[$h][] = basename($r);
		}

		echo '<dl>';

		foreach ($outputs as $hash => $output)
		{
			echo '<dt id="v'.str_replace('.', '', end($results[$hash])).'">Output for ';
			uksort($results[$hash], 'version_compare');
			echo implode(', ', self::groupVersions($results[$hash]));
			echo '</dt><dd>'. $output.'</dd>';
		}

		echo '</dl>';
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

// ob_start(function($html){return str_replace("\t", '', preg_replace("~(\s{2,}|\n)~", '', $html)); });
