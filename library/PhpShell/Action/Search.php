<?php

class PhpShell_Action_Search extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'operation' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => true,
			'options' => ['minLength' => 2, 'maxLength' => 28],
		],
		'operand' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 2],
			'required' => false,
			'options' => ['minLength' => 1, 'maxLength' => 32],
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 3],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	);

	public function run()
	{
		if (isset(Basic::$userinput['operand']))
			$opMatch = "AND operand = ?";

		$this->entries = new PhpShell_ScriptsList(PhpShell_Input, "input IN (SELECT input FROM operations WHERE operation = ? ". $opMatch .")", [Basic::$userinput['operation'], Basic::$userinput['operand']]);

		return parent::run();
	}
}