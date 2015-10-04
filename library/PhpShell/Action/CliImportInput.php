<?php

class PhpShell_Action_CliImportInput extends PhpShell_Action
{
	public function init()
	{
		if (php_sapi_name() != 'cli')
			throw new PhpShell_Action_CliImportInput_CliNotDetectedException('This action can only be run from the cli');

		Basic::$config->PRODUCTION_MODE = true;
		Basic::$config->Template->cachePath = '/tmp';

		parent::init();
	}

	public function run()
	{
		$q = Basic::$database->prepare("INSERT INTO input_source VALUES(?, ?)");

		for ($found = $i = 0; ($i==0 || $found >= $i*250); $i++)
		{
			print '.';

			Basic::$database->beginTransaction();

			foreach (PhpShell_Input::find()->getPage(1+$i, 250) as $input)
			{
				$found++;

				$q->bindParam(1, $input->id, PDO::PARAM_INT);
				$q->bindParam(2, file_get_contents(PhpShell_Input::PATH. $input->short), PDO::PARAM_LOB);
				$q->execute();
			}

			Basic::$database->commit();
		}

		print 'imported '.$found;
	}
}
