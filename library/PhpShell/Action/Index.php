<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'index';

	public function init()
	{
		parent::init();

		$this->last = new PhpShell_LastScriptsList(PhpShell_Input, null, [], ['id' => false]);
	}
}
