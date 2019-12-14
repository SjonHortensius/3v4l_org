<?php

class PhpShell_Action_Cli_FunctionsUpdate extends PhpShell_Action_Cli
{
	const FUNC_PREG = '^(?:static |PHPAPI )?(?:PHP|PHP_NAMED|ZEND)_FUNCTION\((?:php_if_)?';
	// grep PHP_FALIAS ext/standard/basic_functions.c
	private static $alias = [
		'implode' => 'join',
		'rtrim' => 'chop',
		'strstr' => 'strchr',
		'mt_srand' => 'srand',
		'mt_getrandmax' => 'getrandmax',
		'highlight_file' => 'show_source',
		'ini_set' => 'ini_alter',
		'dns_check_record' => 'checkdnsrr',
		'dns_get_mx' => 'getmxrr',
		'floatval' => 'doubleval',
		'is_int' => /*['is_integer', ]*/'is_long',
		'is_float' => 'is_double',
		'fwrite' => 'fputs',
		'stream_set_write_buffer' => 'set_file_buffer',
		'stream_set_blocking' => 'socket_set_blocking',
		'stream_wrapper_register' => 'stream_register_wrapper',
		'stream_set_timeout' => 'socket_set_timeout',
		'stream_get_meta_data' => 'socket_get_status',
		'getdir' => 'dir',
		'is_writable' => 'is_writeable',
		'disk_free_space' => 'diskfreespace',
		'current' => 'pos',
		'count' => 'sizeof',
		'array_key_exists' => 'key_exists',
	];
	private static $dynamicFunctions = [
		// grep FileFunc ext/standard/filestat.c
		'ext/standard/filestat.c:PHPAPI void php_stat' => ['fileperms', 'fileinode', 'filesize', 'fileowner', 'filegroup',
			'fileatime', 'filemtime', 'filectime', 'filetype', 'is_writable', 'is_readable', 'is_executable',
			'is_file', 'is_dir', 'is_link', 'file_exists', 'lstat', 'stat'],
		// grep PHP_ZLIB_ENCODE_FUNC ext/zlib/zlib.c
		'ext/zlib/zlib.c:static zend_string *php_zlib_encode' => ['zlib_encode', 'gzdeflate', 'gzencode', 'gzcompress'],
	];

	public function run(): void
	{
		$result = ['skipped'=>0, 'updated'=>0];

		if (!is_dir('/tmp/php-src/Zend'))
			throw new Exception('FEED ME A STRAY CAT @ `/tmp/php-src/`');
		chdir('/tmp/php-src/');

		Basic::$database->beginTransaction();
		$statement = Basic::$database->prepare("INSERT INTO function (text, source) VALUES (?, ?) ON CONFLICT (text) DO UPDATE SET source=excluded.source");

		foreach (self::$dynamicFunctions as $location => $functions)
		{
			[$path, $preg] = explode(':', $location, 2);
			$lineNo = key(preg_grep('~^'. preg_quote($preg, '~').'~', file($path)));

			foreach ($functions as $function)
				$result['updated'] += intval($statement->execute([$function, $path .':'. $lineNo]));
		}

		$preg = escapeshellarg(self::FUNC_PREG);
		foreach (explode("\n", `grep -nrP $preg --include=*.c ext/ main/ Zend/`) as $line)
		{
			list($file, $lineNo, $match, $trash) = explode(':', $line, 4);

			if (!empty($trash) || !preg_match('~'. self::FUNC_PREG .'(.*)\)(?!;)~', $match, $m) || strlen($m[1]) > 64)
			{
				$result['skipped']++;
				continue;
			}

			$m[1] = trim($m[1]);
			if (in_array($m[1], ['user_sprintf', 'user_printf']))
				$m[1] = substr($m[1], 5);

			$result['updated'] += intval($statement->execute([$m[1], $file.':'.$lineNo]));

			if (isset(self::$alias[$m[1]]))
				$result['updated'] += intval($statement->execute([self::$alias[$m[1]], $file.':'.$lineNo]));
		}

		Basic::$database->commit();

		print_r($result);
	}
}
