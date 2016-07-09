<?php

class PhpShell_Action_CliUpdatePopular extends PhpShell_Action
{
	public function init()
	{
		if (php_sapi_name() != 'cli')
			throw new PhpShell_Action_ImportInput_CliNotDetectedException('This action can only be run from the cli');

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
