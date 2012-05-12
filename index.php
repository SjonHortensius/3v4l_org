<?php

class PHPShell_Action
{
	const IN  = '/var/lxc/php_shell/in/';
	const OUT = '/var/lxc/php_shell/out/';
	protected $_isBusy = false;
	protected $_code = "<?php\n";

	public function __construct($action = 'index')
	{
		$action = $action . ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) .'Action';
		return $this->$action();
	}

	public function indexPostAction()
	{
		if (!isset($_POST['code']))
			return;

		if (strlen($_POST['code']) > 1024)
			self::_exitError(413, 'The submitted code is too large, please limit your input to 1024 bytes');

		$hash = gmp_strval(gmp_init(sha1($_POST['code']), 16), 58);
		$len = 5;

		do
		{
			$short = substr($hash, -$len);
			$len++;

			$exists = file_exists(self::IN.$short);
		}
		while ($exists && sha1($_POST['code']) !== sha1_file(self::IN.$short));

		if (!$exists)
			file_put_contents(self::IN.$short, $_POST['code']);

		die(header('Location: /'. $short, 302));
	}

	public function indexGetAction()
	{
		$file = substr($_SERVER['REQUEST_URI'], 1);

		if ($file)
		{
			if (!preg_match('~^[a-z0-9]+$~i', $file) || !file_exists(self::IN.$file))
				self::_exitError(404, 'The requested script does not exist');

			$this->_code = file_get_contents(self::IN.$file);
			$this->_isBusy = (16749 != fileperms(self::OUT.$file));
		}

		$busy = $this->_isBusy ? ' class="busy"' : '';

?><!DOCTYPE html>
<html dir="ltr" lang="en-US">
<head>
	<title>3v4l.org - online PHP codepad for 50+ PHP versions</title>
	<meta name="keywords" content="php,codepad,fiddle,phpfiddle,shell"/>
	<link rel="stylesheet" href="/s/c.css"/>
</head>
<body>
	<form method="POST" action="/">
		<h1>3v4l.org<small> - online PHP shell, test over 50 different PHP versions!</small></h1>
		<textarea name="code">
<?=htmlspecialchars($this->_code)?>
		</textarea>
		<input type="submit" value="eval();"<?=$busy?>/>
	</form>
<?php
 		if ('XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH'])
 			echo '<script type="text/javascript">isBusy='.($this->_isBusy?'true':'false').';</script>';

		if ($file)
			$this->_getOutput($file);
?>
	<a href="https://twitter.com/3v4l_org" target="_blank">
		Follow us on Twitter
	</a>

	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js"></script>
	<script type="text/javascript" src="/s/c.js"></script>
</body>
</html><?php
	}

	protected function _getOutput($file)
	{
		echo '<dl>';

		$results = array();
		$outputs = array();
		$files = glob(self::OUT.$file .'/*[0-9]');

		if (!$files)
			return;

		natsort($files);
		foreach ($files as $r)
		{
			$output = htmlspecialchars(ltrim(file_get_contents($r), "\n"));

			if (file_exists($r.'-exit'))
				$output .= '<br/><i>Process exited with code <b>'. file_get_contents($r.'-exit').'</b>.</i>';

			$h = crc32($output);
			$outputs[$h] = $output;
			$results[$h][] = basename($r);
		}

		foreach ($outputs as $hash => $output)
		{
			echo '<dt id="v'.str_replace('.', '', reset($results[$hash])).'">Output for ';
			uksort($results[$hash], 'version_compare');
			echo implode(', ', self::groupVersions($results[$hash]));
			echo '</dt><dd>'. $output.'</dd>';
		}

		echo '</dl>';
	}

	protected static function groupVersions(array $versions)
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

	protected static function _exitError($code, $text)
	{
		http_response_code($code);
		die("<h1>Error $code</h1><p>$text</p>");
	}
}

$action = new PHPShell_Action();

// ob_start(function($html){return str_replace("\t", '', preg_replace("~(\s{2,}|\n)~", '', $html)); });
