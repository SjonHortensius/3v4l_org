<?php

class PhpShell_Action_Cli_ImportInput extends PhpShell_Action_Cli
{
	public function init(): void
	{
		parent::init();

		// Disable logging for performance
		Basic::$config->PRODUCTION_MODE = true;
	}

	public function run(): void
	{
		$prev = Basic::$database->query("SELECT MAX(input) FROM input_src")->fetchColumn();

		if (false == $prev)
			$prev = 0;

		for ($found = $i = 0; ($i==0 || $found >= $i*250); $i++)
		{
			print '.'.(0==$found%1E4 ? $found/1E3 : '');

			Basic::$database->beginTransaction();

			foreach (PhpShell_Input::find("id > ?", [$prev], ['id'=>true])->getPage(1+$i, 250)->getSimpleList('short') as $id => $short)
			{
				$found++;
				PhpShell_InputSource::create(['input' => $id, 'raw' => file_get_contents('/srv/http/3v4l.org/in/'. $short)]);
			}

			Basic::$database->commit();
		}

		print 'imported '.$found;
	}
}
