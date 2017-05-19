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
		$this->entries = PhpShell_Input::find()
			->includeVariance()
			->includeOperations()
			->setOrder(['id' => false]);

		if ($_GET['draft']==1)
			$this->entries = $this->entries->getSubset('NOT "runQuick" ISNULL', []);
		elseif ($_GET['mine']==1)
			$this->entries = $this->entries->getSubset('input.run > 0 AND submit.ip = ?', [$_SERVER['REMOTE_ADDR']])
				->addJoin(PhpShell_Submit::class, "submit.input = input.id");
		else
			$this->entries = $this->entries->getSubset('input.run > 0 AND "runQuick" ISNULL', []);

		parent::run();
	}
}