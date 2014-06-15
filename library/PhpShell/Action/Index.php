<?php

class PhpShell_Action_Index extends PhpShell_Action_Script
{
	public function init()
	{
		PhpShell_Action::init();
	}

	public function run()
	{
		$this->code = "<?php\n\n";

		PhpShell_Action::run();
	}
}