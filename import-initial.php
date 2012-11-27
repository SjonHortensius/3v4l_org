#!/usr/bin/php -dopen_basedir=
<?php

$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA foreign_keys = true;");
$db->exec("PRAGMA synchronous = off;");

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
		echo $s->queryString; print_r($values);
		echo $e;
	}

	return $s->fetchAll(PDO::FETCH_ASSOC);
}

if (1)
{
	$base = '/var/lxc/php_shell';
	foreach (glob($base .'/in/*') as $in)
	{
		$in = basename($in);
		query("INSERT INTO input VALUES(?, null, null)", array($in));
		print "\n$in ";

		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base .'/out/'. $in .'/'));
		foreach (new RegexIterator($files, '~/[\d.vld]{3,}$~') as $out)
		{
			$content = str_replace(chr(7), '\\'.chr(7), file_get_contents($out));
			$content = str_replace('/in/'.$in, chr(7), $content);
			$hash = md5($content);

			query("INSERT or IGNORE INTO output VALUES(?, ?)", array($hash, $content));

			print '.';

			list($tu, $ts, $m) = explode(':', file_get_contents($out.'-timing'));
			$exit = file_exists($out.'-exit') ? intval(file_get_contents($out.'-exit')):0;
			query("INSERT INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?, ?)",
				array($in, $hash, basename($out), $exit, filemtime($out), $tu, $ts, $m)
			);
		}
	}
}

query("INSERT INTO input VALUES('', NULL, 'homepage')");
/*******************************/

$fp = fopen('/var/log/httpd/access_log', 'r');

$p = $s = array();
while ($l = fgets($fp))
{
	/*
195.182.52.76 - - [18/Oct/2012:12:11:16 +0200] "POST /new HTTP/1.0" 302 0 "http://3v4l.org/oErPk" "Mozilla/5.0 (Windows NT 6.1; rv:15.0) Gecko/20100101 Firefox/15.0.1"
31.44.93.2 - - [18/Oct/2012:12:11:16 +0200] "GET /STl1T HTTP/1.1" 200 11557 "http://3v4l.org/STl1T" "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:15.0) Gecko/20100101 Firefox/15.0.1"
195.182.52.76 - - [18/Oct/2012:12:11:17 +0200] "GET /3ePj6 HTTP/1.0" 200 1645 "http://3v4l.org/oErPk" "Mozilla/5.0 (Windows NT 6.1; rv:15.0) Gecko/20100101 Firefox/15.0.1"
*/
	if (!preg_match('~([0-9.]+) - - \[(.*?)\] "([A-Z]+) (.*?) HTTP/1.[01]" \d+ \d+ "(.*?)" ".*"~', $l, $m))
	{
//		var_dump('PREG_MISMATCH: '. $l);
		continue;
	}

	$r = array_combine(array('INPUT', 'ip', 'date', 'method', 'url', 'referer'), $m);

	if (strtotime($r['date']) < strtotime('18/Apr/2012:00:00:00 +0200'))
		continue;

	if ('GET' == $r['method'] && '/' == $r['url'])
		continue;

//	print_r($r);

	if ('POST' == $r['method'])
		$p[ $r['ip'] ] = $r;
	elseif ('GET' == $r['method'])
	{
		if (!isset($p[ $r['ip'] ]) || (strtotime($p[ $r['ip'] ]['date']) - strtotime($r['date'])) > 2)
		{
//			print 'IPDATE_MISMATCH'; print_r($p[ $r['ip'] ]); print_r($r);
			continue;
		}

		$hash = substr($r['url'], 1);
		if (substr($p[ $r['ip'] ]['referer'], 0, 16) == 'http://3v4l.org/')
		{
			print 'I';
			list($script) = explode('/', substr($p[ $r['ip'] ]['referer'], 16));

			query("UPDATE input SET source = ? WHERE hash = ? AND source IS NULL", array($script, $hash));
		}

		if (!isset($s[ $r['url'] ]))
		{
			print 'S';
			query("INSERT INTO submit VALUES(?, ?, ?, null, 1)", array($hash, $r['ip'], $p[ $r['ip'] ]['date']));

			$s[ $r['url'] ]	= true;
			unset($p[ $r['ip'] ]);
		}
		else
		{
			print 's';
			query("UPDATE submit SET count=count+1, updated = ? WHERE input = ? AND ip = ?", array($p[ $r['ip'] ]['date'], $hash, $r['ip']));
		}
	}
}

fclose($fp);