#!/usr/bin/php -dopen_basedir=
<?php

if (!defined('STDIN'))
	die('must be run from cli');

define('SCRIPT', $argv[1]);
$base = '/var/lxc/php_shell/out/'. SCRIPT;

function query($statement, array $values)
{
	global $db;
	$s = $db->prepare($statement);
	foreach ($values as $idx => $value)
		$s->bindValue(is_int($idx) ? 1+$idx : $idx, $value);

	try
	{
		$s->execute();
	}
	catch (PDOException $e)
	{
		echo $e;
	}

	return $s->fetchAll(PDO::FETCH_ASSOC);
}

function importVersion($version)
{
	global $base;

	$file = $base .'/'. $version;

print("importing $file\n");
	$content = str_replace(chr(7), '\\'.chr(7), file_get_contents($file));
	$content = str_replace('/in/'.SCRIPT, chr(7), $content);

	$hash = md5($content);
	$exit = file_exists($file .'-exit') ? intval(file_get_contents($file .'-exit')) : 0;
	list($tu, $ts, $m) = explode(':', file_get_contents($file .'-timing'));

	query("INSERT or IGNORE INTO output VALUES(?, ?)", array($hash, $content));
	query("INSERT or REPLACE INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?, ?)",
		array(SCRIPT, $hash, $version, $exit, filemtime($file), $tu, $ts, $m)
	);
}

try
{
	$db = new PDO('sqlite:/srv/http/3v4l.org/db.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->exec("PRAGMA foreign_keys = true;");

	while (16877 == fileperms($base))
	{
		$file = rtrim(`inotifywait -qe close --timeout 1 --format=%f $base`, "\n");

		if (empty($file))
			break;
print("Inotify gave us $file\t");
		// Fuzzy, but we cannot predict which file comes from inotify
		preg_match('~^(.*?)(-timing|-exit|)$~', $file, $m);
		usleep(200000);
		$version = $m[1];

		importVersion($version);

		clearstatcache();
	}

	// If we're done, check if any files were missed
	foreach (query("SELECT version, created FROM result WHERE input = ? AND created < (datetime(? - 300, 'unixepoch'))", array(SCRIPT, filemtime($base))) as $result)
	{
	print("Missed ${result['version']}; ". filemtime($base) ." > ${result['created']}\t");
		importVersion($result['version']);
	}
}
catch (PDOException $e)
{
	echo $e;
}