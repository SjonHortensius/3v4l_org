<?php
# ./cli versionUpdate | sudo -u postgres psql phpshell

class PhpShell_Action_Cli_VersionUpdate extends PhpShell_Action_Cli
{
	const SECURITY_SUPPORT = [
		71 => '2019-12-01',
		72 => '2019-11-30',
		73 => '2020-12-06',
		74 => '2021-11-28',
		80 => '2022-11-26',
		81 => '2023-11-25',
		82 => '2024-12-08',
		82 => '2024-12-08',
		82 => '2024-12-08',
	];

	const EOL = [
		43 => '2005-03-31',
		44 => '2008-08-07',
		50 => '2005-09-05',
		51 => '2006-08-24',
		52 => '2011-01-06',
		53 => '2014-08-14',
		54 => '2015-09-03',
		55 => '2016-07-21',
		56 => '2018-12-31',
		70 => '2019-01-10',
		71 => '2019-12-31',
		72 => '2020-11-30',
		73 => '2021-12-31',
		74 => '2022-11-28',
		80 => '2023-11-26',
		81 => '2025-12-31',
		82 => '2026-12-31',
		83 => '2027-12-31',
		84 => '2028-12-31',
	];

	public function run(): void
	{
		$nextVersionId = Basic::$database->q("SELECT MAX(id) FROM version")->fetchColumn();

		foreach ([4, 5, 7, 8] as $major)
		{
			foreach ($this->_getReleases($major) as $name => $released)
			{
				unset($pendingInsert);

				[$vMajor, $vMinor, $vRelease] = explode('.', $name);
				$order = sprintf('%1d%1d%02d', $vMajor, $vMinor, $vRelease);

				if ($vMajor == 4 && $vMinor < 3)
					continue;

				$eol = self::EOL[ 10 * $vMajor + $vMinor ];

				$command = "/bin/php-$name -c /etc";

				if (!file_exists(APPLICATION_PATH .'/usr_lib_php/'.$name.'/modules/intl.so') || !file_exists(APPLICATION_PATH .'/usr_lib_php/'.$name.'/modules/opcache.so'))
					$command .= '/php_archive.ini';

				if (7 == $vMajor && 1 == $vMinor && $vRelease > 1 && $vRelease < 7 && (file_exists(APPLICATION_PATH .'/usr_lib_php/'.$name.'/modules/opcache.so')))
					$command .= ' -dopcache.enable_cli=0';

				if (file_exists(APPLICATION_PATH .'/usr_lib_php/'.$name.'/modules/sodium.so'))
					$command .= ' -dextension=sodium.so';

				$command .= ' -q';

				try
				{
					$version = PhpShell_Version::byName($name);

					if ($version->released != $released)
						printf("UPDATE version SET released = '%s' WHERE name = '%s' AND released = '%s';\n", $released, $name, $version->released);

					if ($version->order != $order)
						printf("UPDATE version SET \"order\" = %d WHERE name = '%s' AND \"order\" = %d;\n", $order, $name, $version->order);

					if ($version->command != $command)
						printf("UPDATE version SET command = '%s' WHERE name = '%s' AND command = '%s';\n", $command, $name, $version->command);

					if (0&&$version->eol != $eol)
						printf("UPDATE version SET eol = '%s' WHERE name = '%s' AND eol = '%s';\n", $eol, $name, $version->eol);
				}
				catch (Basic_Entity_NotFoundException $e)
				{
					if (!file_exists(APPLICATION_PATH .'/bin/php-'. $name))
					{
						fprintf(STDERR, "[%s] version missing\n", $name);
						continue;
					}

					$pendingInsert = "INSERT INTO version VALUES('$name', '$released', $order, '$command', false, nextval('version_id_seq'), '$eol');";
				}

				$minorIds = [];
				if (isset($pendingInsert))
				{
					echo "BEGIN;\nSELECT SETVAL('version_id_seq', (SELECT MAX(id) FROM version));\n$pendingInsert\n";
					array_push($minorIds, ++$nextVersionId);
				}

				// Verify existing partitions
				if (0 == $vRelease || isset($pendingInsert))
				{
					$minorIds = array_merge($minorIds, $this->_getIdsForMinor($vMajor, $vMinor));
					asort($minorIds);

					$current = Basic::$database->q("
						SELECT pg_get_expr(pt.relpartbound, pt.oid, true)
						FROM pg_class base_tb
						JOIN pg_inherits i ON i.inhparent = base_tb.oid
						JOIN pg_class pt ON pt.oid = i.inhrelid
						WHERE base_tb.oid = 'public.result'::regclass AND pt.relname = ?
					", ['result_php'.$vMajor.$vMinor])->fetchColumn();

					#FIXME when adding multiple minors don't include previously added version in $expect
					$expect = "FOR VALUES IN ('". implode("', '", $minorIds) ."')";
					if ($current != false && $current != $expect)
					{
						printf("ALTER TABLE RESULT DETACH PARTITION result_php$vMajor$vMinor;/*$current*/\n");
						printf("ALTER TABLE RESULT ATTACH PARTITION result_php$vMajor$vMinor   $expect;\n");
					}
				}

				if (isset($pendingInsert))
					echo "COMMIT;\n";
			}
		}
	}

	// return releases but prefix non-existing ones if they are uploaded already
	protected function _getReleases(int $major): Generator
	{
		$json = json_decode(file_get_contents('http://php.net/releases/index.php?json&max=999&version='. $major));

		foreach ($json as $name => $data)
		{
			if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $name, $matches))
			{
				fprintf(STDERR, "[%s] name cannot be parsed\n", $name);
				continue;
			}
			else
				[, $vMajor, $vMinor, $vRelease] = $matches;

			$newer = implode('.', [$vMajor, $vMinor, $vRelease+1]);
			if (!isset($json->$newer) && file_exists(APPLICATION_PATH .'/bin/php-'. $newer) && !in_array($newer, ['5.2.7']))
				yield $newer => date('Y-m-d', strtotime('tomorrow'));

			// Sometimes new archive-formats get introduced meaning source-date is too new
			// Sometimes PHP sucks and version.date is updated when another version is released
			// Some older releases have no source-dates
			$released = date('Y-m-d', min(strtotime($data->date ?? $data->source[1]->date), strtotime($data->source[0]->date ?? $data->date)));

			yield $name => $released;
		}
	}

	protected function _getIdsForMinor(int $major, int $minor): array
	{
		return explode(',', trim(Basic::$database->q("SELECT ARRAY_AGG(id) FROM version WHERE name LIKE ?", ["$major.$minor.%"])->fetchColumn() ?? '', '{}'));

	}
}
