<?php

ini_set('memory_limit', '1G');

function report($newName = null)
{
	static $start, $done, $prev, $name;
	$now = microtime(1);

	if (!isset($start) || isset($newName))
		$prev = $start = $now;

	$done++;

	if (isset($newName))
	{
		$name = $newName;
		$done = 0;
	}
	elseif ($now - $prev>=0.5)
	{
		$prev = $now;
		printf("\r%s - %d done; %d / sec", $name, $done, ($done/($now-$start)));
	}
}

class p extends PDO
{
	public function __construct($dsn, $username = null)
	{
		parent::__construct($dsn, $username, $passwd, $options);
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	function query($statement, $parameters = array(), $fetch = true)
	{
		if (!is_object($statement))
			$statement = $this->prepare($statement);

		try
		{
			$statement->execute($parameters);
		}
		catch (PDOException $e)
		{
			var_dump(func_get_args());
			die($e);
		}

		report();

		if ($fetch)
			return $statement->fetchAll(PDO::FETCH_ASSOC);
		else
			return $statement;
	}
}

$s = new p('sqlite:db.sqlite');
$p = new p('pgsql:host=localhost;dbname=phpshell', 'phpshell', '');

report('truncate');
$p->query("TRUNCATE TABLE output *");
$p->query("TRUNCATE TABLE result CASCADE");
$p->query("TRUNCATE TABLE version CASCADE");
$p->query("TRUNCATE TABLE submit");
$p->query("TRUNCATE TABLE input");
$p->query("TRUNCATE TABLE \"references\"");

foreach ($s->query("SELECT * from \"references\" ORDER BY parent ASC") as $row)
	$p->query("INSERT INTO \"references\" VALUES(". rtrim(str_repeat('?,', count($row)), ',') .")", array_values($row));

report('versions');

foreach ($s->query("SELECT * from version") as $row)
	$p->query("INSERT INTO version VALUES(". rtrim(str_repeat('?,', count($row)), ',') .")", array_values($row));

report('input');

$p->query("BEGIN");
$p->query("SET CONSTRAINTS ALL DEFERRED");
foreach ($s->query("SELECT * from input") as $row)
{
	if (!isset($row['state']))
		$row['state'] = 'done';
	$p->query("INSERT INTO input VALUES(". rtrim(str_repeat('?,', count($row)), ',') .")", array_values($row));
}
$p->query("COMMIT");

report('output');

$o = array();

foreach ($s->query("SELECT * from output") as $row)
{
	$o[ $row['hash'] ] = base64_encode(sha1($row['raw'], 1));
	$row['hash'] = $o[ $row['hash'] ];

	$statement = $p->prepare("INSERT INTO output VALUES(". rtrim(str_repeat('?,', count($row)), ','). ")");
	$statement->bindParam(1, $row['hash']);
	$statement->bindParam(2, $row['raw'], PDO::PARAM_LOB);

	$p->query($statement, null, false);
}

report('results');

$statementResults = $s->query("SELECT * from result", array(), false);
while ($row = $statementResults->fetch(PDO::FETCH_ASSOC))
#foreach ($s->query("SELECT * from result", array(), false) as $row)
{
	$row['output'] = $o[ $row['output'] ];
	$p->query("INSERT INTO result VALUES(". rtrim(str_repeat('?,', count($row)), ',') .")", array_values($row));
}
unset($o);

report('submits');

foreach ($s->query("SELECT * from submit") as $row)
	$p->query("INSERT INTO submit VALUES(". rtrim(str_repeat('?,', count($row)), ',') .")", array_values($row));

// Clean
$s->query("DELETE FROM output WHERE hash NOT IN (SELECT DISTINCT output FROM result);");
