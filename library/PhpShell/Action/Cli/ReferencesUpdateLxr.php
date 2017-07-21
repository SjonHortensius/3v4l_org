<?php

class PhpShell_Action_Cli_ReferencesUpdateLxr extends PhpShell_Action_Cli
{
	public function run(): void
	{
		if (!is_dir('/tmp/php-src/Zend'))
			throw new Exception('FEED ME A STRAY CAT @ `/tmp/php-src/`');

		chdir('/tmp/php-src/');
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

			PhpShell_Reference::find("operation = 'INIT_FCALL' AND operand = ?", [$match])->delete();

			print $match." ";

			$parent = null;
			if (0 === strpos($match, 'preg_'))
				$parent = PhpShell_Reference::find("link LIKE '%/book.pcre.php'")->getSingle()->id;
			elseif (0 === strpos($match, 'ob_'))
				$parent = PhpShell_Reference::find("link LIKE '%/book.outcontrol.php'")->getSingle()->id;
			elseif ($file == 'ext/standard/string.c')
				$parent = PhpShell_Reference::find("link LIKE '%/book.strings.php'")->getSingle()->id;
			elseif ($file == 'ext/date/php_date.c')
				$parent = PhpShell_Reference::find("link LIKE '%/book.datetime.php'")->getSingle()->id;

			Basic::$database->query(
				"INSERT INTO \"references\" (operation, operand, link, name, parent) VALUES (?, ?, ?, ?, ?)",
				['INIT_FCALL', $match, 'http://php.net/manual/en/function.'.str_replace('_', '-', $match).'.php', $match.' - manual', $parent]);
			Basic::$database->query(
				"INSERT INTO \"references\" (operation, operand, link, name) VALUES (?, ?, ?, ?)",
				['INIT_FCALL', $match, 'http://lxr.room11.org/xref/php-src@master/'.$file.'#'.$line, $match.' - source']);
		}
	}
}
