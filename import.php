#!/usr/bin/php
<?php

if (!defined('STDIN'))
	die('must be run from cli');

define('SCRIPT', $argv[1]);
define('VERSION', $argv[2]);
define('BASE', '/var/lxc/php_shell/out/'. SCRIPT);

$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA foreign_keys = true;");

function query($statement, array $values)
{
	global $db;
	$s = $db->prepare($statement);
	foreach ($values as $idx => $value)
		$s->bindValue(is_int($idx) ? 1+$idx : $idx, $value);

	$s->execute();
	return $s->fetchAll(PDO::FETCH_ASSOC);
}

$content = file_get_contents(BASE .'/'. VERSION);
query("INSERT or IGNORE INTO output VALUES(?, ?)", array(md5($content), $content));

list($tu, $ts) = explode(':', file_get_contents(BASE .'/'. VERSION .'-timing'));
$exit = file_exists($r.'-exit') ? file_get_contents(BASE .'/'. VERSION .'-exit') : 0;

query("INSERT or UPDATE INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?)", array(
	$short, $hash, basename($r), $exit, filemtime($r), $tu, $ts
));