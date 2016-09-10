<?php

class PhpShell_Action_Cli_UpdatePopular extends PhpShell_Action_Cli
{
	public function init()
	{
		Basic::$config->Template->cachePath = '/tmp/';

		parent::init();
	}

	public function run()
	{
		$active = [];
		while (FALSE !== ($line = fgets(STDIN)))
		{
			list($count, $url) = explode(' ', ltrim($line));

			if ($url != '/')
				$active[ trim($url) ] = $count;
		}

		Basic::$cache->set('active_scripts', $active, 86400);
	}
}
