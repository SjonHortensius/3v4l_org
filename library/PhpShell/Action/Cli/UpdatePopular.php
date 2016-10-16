<?php

class PhpShell_Action_Cli_UpdatePopular extends PhpShell_Action_Cli
{
	public function run()
	{
		$head = shell_exec('grep -vF compatible /var/log/nginx/access_log | tail -n 50000 |cut -d\" -f2,6|grep -v ^POST|sort -u|cut -d" " -f2|uniq -c|sort -nr 2>/dev/null|head -n10');

		$active = [];
		foreach (explode("\n", $head) as $line)
		{
			list($count, $url) = explode(' ', ltrim($line));

			if ($url != '/' && false === strpos($url, '.json'))
				$active[ trim($url) ] = $count;
		}

		Basic::$cache->set('active_scripts', $active, 86400);
	}
}
