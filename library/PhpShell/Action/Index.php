<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'index';
	public $lastModified = 'now';
	public $cacheLength = '3 minutes';

	public function init()
	{
		parent::init();

		$this->last = new PhpShell_LastScriptsList(PhpShell_Input, 'input.run > 0', [], ['id' => false]);
	}
}