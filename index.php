<?php
define('IN', '/var/lxc/php_shell/in/');
define('OUT', '/var/lxc/php_shell/out/');

if (isset($_POST['code']))
{
	if (strlen($_POST['code']) > 1024)
		die(header('HTTP/1.1 413 Submitted Code Too Large', 413). '<h1>Error 413</h1><p>The submitted code is too large, please limit your input to 1024 bytes</p>');

	$hash = gmp_strval(gmp_init(sha1($_POST['code']), 16), 58);
	$len = 5;

	do
	{
		$short = substr($hash, -$len);
		$len++;

		$exists = file_exists(IN.$short);
	}
	while ($exists && sha1($_POST['code']) !== sha1_file(IN.$short));

	if (!$exists)
		file_put_contents(IN.$short, $_POST['code']);

	die(header('Location: /'. $short, 302));
}

$short = substr($_SERVER['REQUEST_URI'], 1);
if (!empty($short))
{
	if (!preg_match('~^[a-z0-9]+$~i', $short) || !file_exists(IN.$short))
		die(header('HTTP/1.1 404 Not Found', 404). '<h1>Error 404</h1><p>The requested script does not exist</p>');

	$_POST['code'] = file_get_contents(IN.$short);
	define('FILE', $short);
	
	$isBusy = (16749 != fileperms(OUT.$short));
}

?><!DOCTYPE html><html dir="ltr" lang="en-US"><head><title>3v4l.org is very dangerous because it allows execution of arbitrary PHP code</title><link rel="stylesheet" href="/s/c.css" /><script type="text/javascript" src="/s/c.js"></script></head><body><form method="post" action="/"><h1>3v4l.org<small> - online PHP shell, test over 50 different PHP versions!</small></h1><textarea id="code" name="code"><?php
if (isset($_POST['code']))
	echo htmlspecialchars($_POST['code']);
else
	echo '<?php'."\n";

echo '</textarea><br /><input type="submit" value="eval();"'.($isBusy?' class="busy"':'').' /></form>';

if (!defined('FILE'))
	die('<span><a href="https://twitter.com/3v4l_org" target="_blank">Follow us on Twitter</a><span></body></html>');

echo '<dl>';

$results = array();
$outputs = array();
$files = glob(OUT.FILE .'/*[0-9]');
natsort($files);
foreach ($files as $r)
{
	$output = htmlspecialchars(file_get_contents($r));

	if (file_exists($r.'-exit'))
		$output .= '<br/><i>Process exited with code <b>'. file_get_contents($r.'-exit').'</b>.</i>';

	$h = md5($output);
	$outputs[$h] = $output;
	$results[$h][] = basename($r);
}

foreach ($outputs as $hash => $output)
{
	echo '<dt>Output for ';
	uksort($results[$hash], 'version_compare');

	echo implode(', ', group_stuff($results[$hash]));
	echo '</dt><dd><pre>'. $output.'</pre></dd>';
}

echo '</dl>';

function group_stuff(array $versions)
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

?>
<span><a href="https://twitter.com/3v4l_org" target="_blank">Follow us on Twitter</a><span></body></html><?php
