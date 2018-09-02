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
		$statement = Basic::$database->prepare("INSERT INTO \"references\" (function, link, name) VALUES (?, ?, ?)");

		foreach (explode("\n", `grep -nrP '^(?:static )?(PHP|ZEND)_FUNCTION\(' --include=*.c ext/ main/ Zend/`) as $line)
		{
			list($file, $line, $match, $trash) = explode(':', $line, 4);

			if (!empty($trash))
			{
				fprintf(STDERR, "mismatch: %s:%s:%s followed by trash: %s\n", $file, $line, $match, $trash);
				continue;
			}

			if (!preg_match('~^(?:static )?(?:ZEND|PHP)_FUNCTION\((.*)\)(?!;)~', $match, $m) || strlen($m[1]) > 64)
			{
				fprintf(STDERR, "skipping: %s\n", $match);
				continue;
			}
			$match = trim($m[1]);

			print $match." ";

			$statement->execute([$match, 'http://php.net/manual/en/function.'.str_replace('_', '-', $match).'.php', $match.' - manual']);
			$statement->execute([$match, 'https://lxr.room11.org/xref/php-src@master/'.$file.'#'.$line, $match.' - source']);
		}

		Basic::$database->commit();
	}
}
