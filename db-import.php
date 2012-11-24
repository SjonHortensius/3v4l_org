<?php
$db = new PDO('sqlite:/tmp/db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA foreign_keys = true;");
$db->exec("PRAGMA synchronous = off;");

function query($statement, array $values)
{
	global $db;
	$s = $db->prepare($statement);
	foreach ($values as $idx => $value)
		$s->bindValue(is_int($idx) ? 1+$idx : $idx, $value);

	$s->execute();
	return $s->fetchAll(PDO::FETCH_ASSOC);
}

if (1)
{
	$base = '/var/backup/3v4l.org/var/lxc/php_shell';
	foreach (glob($base .'/in/*') as $in)
	{
		$in = basename($in);
		query("INSERT INTO input VALUES(?, null, null)", array($in));
		print "\n$in ";

		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base .'/out/'. $in .'/'));
		foreach (new RegexIterator($files, '~/[\d.vld]{3,}$~') as $out)
		{
			$content = str_replace('/in/'.$in, chr(7), file_get_contents($out));
			$hash = md5($content);

			query("INSERT or IGNORE INTO output VALUES(?, ?)", array($hash, $content));

			print '.';

			list($tu, $ts) = explode(':', file_get_contents($out.'-timing'));
			$exit = file_exists($out.'-exit') ? intval(file_get_contents($out.'-exit')):0;
			query("INSERT INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?)", array($in, $hash, basename($out), $exit, filemtime($out), $tu, $ts));
		}
	}
}

/*******************************/

$fp = fopen('/var/backup/3v4l.org/var/log/httpd/access_log', 'r');

$p = $s = array();
while ($l = fgets($fp))
{
	if (!preg_match('~([0-9.]+) - - \[(.*?)\] "([A-Z]+) (.*?) HTTP/1.1" \d+ \d+ "(.*?)" ".*"~', $l, $m))
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
			list($script) = explode('/', substr($p[ $r['ip'] ]['referer'], 16));

			try
			{
				query("UPDATE input SET source = ? WHERE hash = ? AND source IS NULL", array($script, $hash));
				print 'I';
			}
			catch (PDOException $e)
			{
				echo $e;
			}
		}

		if (!isset($s[ $r['url'] ]))
		{
			try
			{
				query("INSERT INTO submit VALUES(?, ?, ?, null, 1)", array($hash, $r['ip'], $p[ $r['ip'] ]['date']));
				print 'S';
			}
			catch (PDOException $e)
			{
				echo $e;
			}

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