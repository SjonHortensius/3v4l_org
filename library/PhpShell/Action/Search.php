<?php

class PhpShell_Action_Search extends PhpShell_Action
{
	public $title = 'Search through all scripts';
	public $formSubmit = 'array_search();';
	public $userinputConfig = [
		'query' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => true,
			'minLength' => 2,
			'maxLength' => 28,
			'description' => 'sql LIKE syntax supported, eg. array\_%',
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'default' => 1,
			'minValue' => 1,
			'maxValue' => 9,
		],
		'sort' => [
			'source' => ['superglobal' => 'GET'],
			'values' => [
				'created' => 'input.id + 0'
			],
		],
		'order' => [
			'source' => ['superglobal' => 'GET'],
			'values' => ['asc', 'desc'],
		],
	];
	protected $_cacheLength = '24 hours';
	public $entries;

	public function init(): void
	{
		if (isset($_REQUEST[1]))
			Basic::$userinput->query->setValue(rawurldecode($_REQUEST[1]));

		parent::init();
	}

	public function run(): void
	{
		$this->entries = PhpShell_FunctionCall::find()
			->addJoin(PhpShell_Function::class, "function.id = \"functionCall\".function", null, 'INNER', false)
			->getSubset("function.text LIKE ?", [Basic::$userinput['query']])
			->addJoin(PhpShell_Input::class, "input.id = \"functionCall\".input")
			->getSubset("input.state = 'done'")
			->includeFunctionCalls()
			->setOrder(['input.id + 0' => boolval('asc' == Basic::$userinput['order'])]);

		parent::run();
	}
}