<?php

class PhpShell_Action_Last extends PhpShell_Action
{
	public $title = 'Recent submissions';
	public $userinputConfig = [
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'default' => 1,
			'minValue' => 1,
			'maxValue' => 9,
		],
		'mine' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'GET', 'key' => 'mine'],
			'values' => [1, 0],
		],
		'draft' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'GET', 'key' => 'draft'],
			'values' => [1, 0],
		],
	];
	public $entries;

	public function run(): void
	{
		$this->entries = PhpShell_Input::find()
			->includeVariance()
			->includeFunctionCalls()
			->setOrder(['id' => false])
			->addJoin(PhpShell_Submit::class, "submit.input = input.id", null, 'INNER', false);

		if (!Basic::$userinput['draft'])
			$this->entries = $this->entries->getSubset('NOT submit."isQuick"');

		if (Basic::$userinput['mine'])
			$this->entries = $this->entries->getSubset('submit.ip = ?', [$_SERVER['REMOTE_ADDR']]);

		parent::run();
	}
}