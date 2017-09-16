<?php

class PhpShell_Action_Last extends PhpShell_Action
{
	public $title = 'Recent submissions';
	public $userinputConfig = [
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
		'mine' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'GET', 'key' => 'mine'],
			'values' => [1, 0],
		],
	];
	public $entries;

	public function run(): void
	{
		$this->entries = PhpShell_Input::find()
			->includeVariance()
			->includeOperations()
			->setOrder(['id' => false]);

		if ($_GET['draft']==1)
			$this->entries = $this->entries->getSubset('NOT "runQuick" ISNULL', []);
		else
			$this->entries = $this->entries->getSubset('input.run > 0 AND "runQuick" ISNULL', []);

		if (Basic::$userinput['mine'])
			$this->entries = $this->entries->getSubset('submit.ip = ?', [$_SERVER['REMOTE_ADDR']])
				->addJoin(PhpShell_Submit::class, "submit.input = input.id");

		parent::run();
	}
}