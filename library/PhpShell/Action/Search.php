<?php

class PhpShell_Action_Search extends PhpShell_Action_Tagcloud
{
	public $title = 'Search through all scripts';
	public $formSubmit = 'array_search();';
	public $userinputConfig = [
		'query' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => true,
			'options' => ['minLength' => 2, 'maxLength' => 28],
			'description' => 'sql LIKE syntax supported, eg. array\_%',
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	];
	protected $_cacheLength = '24 hours';
	public $entries;

	public function init(): void
	{
		if (isset($_REQUEST[1]))
			Basic::$userinput->query->setValue(rawurldecode($_REQUEST[1]));

		// for tagcloud on form-page
		if (!Basic::$userinput->query->isValid())
			parent::generate();

		parent::init();
	}

	public function run(): void
	{
		$this->entries = PhpShell_Input::find()
			->getSubset("input.state = 'done'")
			->includePerformance()
			->setOrder(['input.id' => false]);

/*		if (preg_match('^[^a-z0-9_]+$', Basic::$userinput['query']))
		{
			$this->entries = $this->entries
				->addJoin(PhpShell_Functioncalls::class, "functioncall.input = input.id")
				->getSubset("function = ?", [Basic::$userinput['query']]);
		}
		else
		{
*/			$this->entries = $this->entries
				->addJoin(PhpShell_InputSource::class, "input_src.input = input.id")
				->getSubset("raw LIKE ?", ['%'. str_replace('\\', '\\\\', Basic::$userinput['query']). '%']);
#		}

		parent::run();
	}
}