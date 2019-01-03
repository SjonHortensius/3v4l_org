<?php

class PhpShell_Action_Cli_FunctionsUpdate extends PhpShell_Action_Cli
{
	const FUNC_PREG = '^(?:static |PHPAPI )?(?:PHP|ZEND)_FUNCTION\(';

	public function run(): void
	{
		if (!is_dir('/tmp/php-src/Zend'))
			throw new Exception('FEED ME A STRAY CAT @ `/tmp/php-src/`');
		chdir('/tmp/php-src/');

		Basic::$database->beginTransaction();
		$statement = Basic::$database->prepare("INSERT INTO function (text, source) VALUES (?, ?) ON CONFLICT (text) DO UPDATE SET source=excluded.source");

		$preg = self::FUNC_PREG;
		foreach (explode("\n", `grep -nrP '$preg' --include=*.c ext/ main/ Zend/`) as $line)
		{
			list($file, $lineNo, $match, $trash) = explode(':', $line, 4);

			if (!empty($trash) || !preg_match('~'. self::FUNC_PREG .'(.*)\)(?!;)~', $match, $m) || strlen($m[1]) > 64)
				;#fprintf(STDERR, "mismatch, skipping: %s\n", $line);
			else
				$statement->execute([trim($m[1]), $file.':'.$lineNo]);
		}

		Basic::$database->commit();
	}
}
