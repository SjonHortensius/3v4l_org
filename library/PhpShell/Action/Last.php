<?php

class PhpShell_Action_Last extends PhpShell_Action
{
	public $bodyClass = 'last';

	protected $_userinputConfig = array(
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	);
	public $entries;

	public function run()
	{
		$this->entries = new PhpShell_ScriptsList(PhpShell_Input, 'input IN (SELECT input FROM submit ORDER BY created DESC LIMIT 99) AND NOT "isHelper"');

		parent::run();
	}
}