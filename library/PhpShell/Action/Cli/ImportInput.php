<?php

class PhpShell_Action_Cli_ImportInput extends PhpShell_Action_Cli
{
	public function init()
	{
		parent::init();

		// Disable logging for performance
		Basic::$config->PRODUCTION_MODE = true;
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
