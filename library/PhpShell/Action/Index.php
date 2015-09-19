<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'script';
	public $lastModified = 'now';
	protected $_cacheLength = '45 seconds';

	public function init()
	{
		parent::init();

		$this->last = new PhpShell_LastScriptsList(PhpShell_Input, 'input.run > 0', [], ['id' => false]);
	}
}