<?php

class PhpShell_Action_Search extends PhpShell_Action_Tagcloud
{
	public $title = 'Search our database for certain opcodes';
	public $formSubmit = 'array_search();';
	public $userinputConfig = array(
		'operation' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => true,
			'options' => ['minLength' => 2, 'maxLength' => 28],
			'inputType' => 'select',
		],
		'operand' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'required' => false,
			'options' => [
				'minLength' => 1,
				'maxLength' => 32,
				'placeholder' => 'optional',
			],
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 3],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	);
	protected $_cacheLength = '24 hours';
	public $haveOperand = [];

	public function init()
	{
		$opCount = [];
		foreach (Basic::$database->query("SELECT * FROM search_operationCount")->fetchArray('count', 'operation') as $op => $count)
			$opCount[$op] = $op .' ('. number_format($count) .' occurrences)';
		Basic::$userinput->operation->values = $opCount;

		if (isset($_REQUEST[1]))
			Basic::$userinput->operation->setValue($_REQUEST[1]);
		if (isset($_REQUEST[2]))
			Basic::$userinput->operand->setValue($_REQUEST[2]);

		$this->haveOperand = Basic::$database->query("SELECT * FROM search_haveOperand")->fetchArray('operation');

		// for the tagcloud
		if (!Basic::$userinput->operation->isValid())
			parent::generate();

		parent::init();
	}

	public function run()
	{
		$this->entries = PhpShell_Input::find()
			->includePerformance()
			->getSubset("input.state = 'done' AND operation = ?", [Basic::$userinput['operation']])
			->addJoin(PhpShell_Operation::class, "operations.input = input.id")
			->setOrder(['input.id' => false]);

		if (isset(Basic::$userinput['operand']))
			$this->entries = $this->entries->getSubset("operand = ?", [Basic::$userinput['operand']]);

		return parent::run();
	}
}