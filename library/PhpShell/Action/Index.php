<?php

class PhpShell_Action_Index extends PhpShell_Action_Script
{
	public function init()
	{
		parent::init();
		Basic::$userinput->script->required = false;
	}

	public function run()
	{
		$this->code = "<?php\n\n\n";

		PhpShell_Action::run();
	}
}