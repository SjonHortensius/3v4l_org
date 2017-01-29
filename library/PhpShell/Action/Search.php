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
	public $noOperand;

	public function init()
	{
		$opCount = Basic::$cache->get(__CLASS__.'::counts', function(){
			$v= [];
			foreach (PhpShell_Operation::find()->getCount('operation') as $op => $count)
				$v[$op] = $op .' ('. number_format($count) .' occurrences)';
			return $v;
		}, (86400/3*2));

		Basic::$userinput->operation->values = $opCount;

		if (isset($_REQUEST[1]))
			Basic::$userinput->operation->setValue($_REQUEST[1]);
		if (isset($_REQUEST[2]))
			Basic::$userinput->operand->setValue($_REQUEST[2]);

		$this->haveOperand = Basic::$cache->get(__CLASS__.'::haveOperand', function(){
			$ops = [];
			foreach (Basic::$database->query("SELECT DISTINCT operation FROM operations WHERE NOT operand ISNULL") as $row)
				array_push($ops, $row['operation']);
			return $ops;
		}, 86400);

		// for the tagcloud
		if (!Basic::$userinput->operation->isValid())
			parent::generate();

		parent::init();
	}

	public function run()
	{
		$q = "input.state = 'done' AND operation = ?";
		$params = array(Basic::$userinput['operation']);

		if (isset(Basic::$userinput['operand']))
		{
			$q .= " AND operand = ?";
			array_push($params, Basic::$userinput['operand']);
		}

		$this->entries = new PhpShell_SearchScriptsList(PhpShell_Input, $q, $params, ['input.id' => !true]);
		//this produces incomplete variance and ~time but is ~4 times faster
		$this->entries->addJoin('result_current', "result_current.input = input.id AND result_current.version >= 32");
		$this->entries->addJoin('operations', "operations.input = input.id");

		return parent::run();
	}
}