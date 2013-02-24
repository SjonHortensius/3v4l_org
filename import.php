#!/usr/bin/php -dopen_basedir=
<?php

if (!defined('STDIN'))
	die('must be run from cli');

define('BASE', '/var/lxc/php_shell');
define('SCRIPT', $argv[1]);
define('VERSION', $argv[2]);

function query($statement, array $parameters)
{
	global $db;
	$s = $db->prepare($statement);

	try
	{
		$s->execute($parameters);
	}
	catch (PDOException $e)
	{
		echo $e;
	}

	return $s->fetchAll(PDO::FETCH_ASSOC);
}

try
{
	$db = new PDO('sqlite:/srv/http/3v4l.org/db.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->exec("PRAGMA foreign_keys = true;");

	$file = BASE .'/out/'. SCRIPT .'/'. VERSION;

	$content = str_replace(chr(7), '\\'.chr(7), file_get_contents($file));
	$content = str_replace('/in/'.SCRIPT, chr(7), $content);

	$hash = md5($content);
	$exit = file_exists($file .'-exit') ? intval(file_get_contents($file .'-exit')) : 0;
	list($tu, $ts, $m) = explode(':', file_get_contents($file .'-timing'));

	query("INSERT or IGNORE INTO output VALUES(?, ?)", array($hash, $content));
	query("INSERT or REPLACE INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?, ?)",
		array(SCRIPT, $hash, VERSION, $exit, filemtime($file), $tu, $ts, $m)
	);

	foreach (array($file, $file.'-exit', $file.'-timing') as $f)
		@unlink($f);
}
catch (PDOException $e)
{
	echo $e;
}

print("imported $file\n");