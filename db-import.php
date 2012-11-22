<?php
$db = new PDO('sqlite:/tmp/db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA foreign_keys = true;");

$qi = $db->prepare("INSERT INTO input VALUES(?, null, null)");
$qo = $db->prepare("INSERT or IGNORE INTO output VALUES(?, ?)");
$qr = $db->prepare("INSERT INTO result VALUES(?, ?, ?, ?, datetime(?, 'unixepoch'), ?, ?)");
$qs = $db->prepare("INSERT INTO submit VALUES(?, ?, ?, ?, ?)");

if (1)
{
$base = '/var/backup/3v4l.org/var/lxc/php_shell';
foreach (glob($base .'/in/*') as $in)
{
	$in = basename($in);
	$qi->bindValue(1, $in);
	$qi->execute();
	print "\n$in";

	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base .'/out/'. $in .'/'));
	foreach (new RegexIterator($files, '~/[\d.vld]{3,}$~') as $out)
	{
#		$content = str_replace('/in/'.$in, chr(7), file_get_contents($out));
		$content = file_get_contents($out);
		$hash = md5($content);
		$qo->bindValue(1, $hash);
		$qo->bindValue(2, $content);
		$qo->execute();
		print basename($out) .' ';

		list($tu, $ts) = explode(':', file_get_contents($out.'-timing'));
		$qr->bindValue(1, $in);
		$qr->bindValue(2, $hash);
		$qr->bindValue(3, basename($out));
		$qr->bindValue(4, file_exists($out.'-exit') ? file_get_contents($out.'-exit'):0, PDO::PARAM_INT);
		$qr->bindValue(5, filemtime($out));
		$qr->bindValue(6, $tu);
		$qr->bindValue(7, $ts);
		$qr->execute();
	}
}
}

/*******************************/

$fp = fopen('access_log', 'r');

$p = $s = array();
while ($l = fgets($fp))
{
	print '.';

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
			print 's';
			list($script) = explode('/', substr($p[ $r['ip'] ]['referer'], 16));
			$q = $db->prepare("UPDATE input SET source = ? WHERE hash = ? AND source IS NULL");
			$q->bindValue(1, $script);
			$q->bindValue(2, $hash);
			try
			{
				$q->execute();
			}
			catch (PDOException $e)
			{}
		}

		if (!isset($s[ $r['url'] ]))
		{
			print 'I';
			$qs->bindValue(1, $hash);
			$qs->bindValue(2, $r['ip']);
			$qs->bindValue(3, $p[ $r['ip'] ]['date']);
			$qs->bindValue(4, null);
			$qs->bindValue(5, 1);
			try
			{
				$qs->execute();
			}
			catch (PDOException $e)
			{
				echo "Error executing ". $qs->queryString;
			}
			$s[ $r['url'] ]	= true;
			unset($p[ $r['ip'] ]);
		}
		else
		{
			print 'u';
			$q = $db->prepare("UPDATE submit SET count=count+1, updated = ? WHERE input = ? AND ip = ?");
			$q->bindValue(1, $p[ $r['ip'] ]['date']);
			$q->bindValue(2, $hash);
			$q->bindValue(3, $r['ip']);
			$q->execute();
		}
	}
}

fclose($fp);
