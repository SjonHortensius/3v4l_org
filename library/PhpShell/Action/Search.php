<?php

class PhpShell_Action_Search extends PhpShell_Action
{
	public $formSubmit = 'array_search();';
	protected $_userinputConfig = array(
		'operation' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => true,
			'options' => ['minLength' => 2, 'maxLength' => 28],
			'inputType' => 'select',
		],
		'operand' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 2],
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

	public function init()
	{
		global $_MULTIVIEW;

		if (isset($_MULTIVIEW[1]))
			$_POST['operation'] = $_MULTIVIEW[1];
		if (isset($_MULTIVIEW[2]))
			$_POST['operand'] = $_MULTIVIEW[2];

		$opCount = Basic::$cache->get(__CLASS__.'::counts', function(){
			$opCount = [];
			foreach (PhpShell_Operation::find()->getCount('operation') as $op => $count)
				$opCount[$op] = $op .' ('. $count .' occurrences)';
			return $opCount;
		});

		$this->_userinputConfig['operation']['values'] = $opCount;

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

		$this->entries = new PhpShell_SearchScriptsList(PhpShell_Input, $q, $params);

		return parent::run();
	}
}