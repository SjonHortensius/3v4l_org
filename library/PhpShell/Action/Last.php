<?php

class PhpShell_Action_Last extends PhpShell_Action
{
	public $title = 'Recent submissions';
	public $userinputConfig = array(
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	);
	public $entries;

	public function run()
	{
		if ($_GET['draft']==1)
			$this->entries = new PhpShell_LastScriptsList(PhpShell_Input, 'NOT "runQuick" ISNULL', [], ['id' => false]);
		elseif ($_GET['mine']==1)
		{
			$this->entries = new PhpShell_LastScriptsList(PhpShell_Input, 'input.run > 0 AND submit.ip = ?', [$_SERVER['REMOTE_ADDR']], ['id' => false]);
			$this->entries->addJoin('submit', "submit.input = input.id");
		}
		else
			$this->entries = new PhpShell_LastScriptsList(PhpShell_Input, 'input.run > 0 AND "runQuick" ISNULL', [], ['id' => false]);

		$this->entries->addJoin('result', "result.input = input.id AND result.version >= 32");

		parent::run();
	}
}