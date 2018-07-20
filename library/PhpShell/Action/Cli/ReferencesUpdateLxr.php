<?php

class PhpShell_Action_Cli_ReferencesUpdateLxr extends PhpShell_Action_Cli
{
	public function run(): void
	{
		if (!is_dir('/tmp/php-src/Zend'))
			throw new Exception('FEED ME A STRAY CAT @ `/tmp/php-src/`');
		chdir('/tmp/php-src/');

		Basic::$database->beginTransaction();
		Basic::$database->query("DELETE FROM \"references\"");
		$statement = Basic::$database->prepare("INSERT INTO \"references\" (operation, operand, link, name) VALUES (?, ?, ?, ?)");

		foreach (explode("\n", `grep -nrE '^(PHP|ZEND)_FUNCTION\(' ext/ main/ Zend/|grep -F .c:`) as $line)
		{
			list($file, $line, $match, $trash) = explode(':', $line, 4);

			if (!empty($trash))
			{
				fprintf(STDERR, "mismatch: %s:%s:%s followed by trash: %s\n", $file, $line, $match, $trash);
				continue;
			}

			if (!preg_match('~^(?:ZEND|PHP)_FUNCTION\((.*)\)(?!;)~', $match, $m) || strlen($m[1]) > 64)
			{
				fprintf(STDERR, "skipping: %s\n", $match);
				continue;
			}
			$match = $m[1];

			print $match." ";

			$statement->execute(['INIT_FCALL', $match, 'http://php.net/manual/en/function.'.str_replace('_', '-', $match).'.php', $match.' - manual']);
			$statement->execute(['INIT_FCALL', $match, 'https://lxr.room11.org/xref/php-src@master/'.$file.'#'.$line, $match.' - source']);
		}

		Basic::$database->commit();
	}
}
