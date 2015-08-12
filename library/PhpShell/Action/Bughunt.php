<?php

class PhpShell_Action_Bughunt extends PhpShell_Action
{
	public $formSubmit = 'array_intersect_uassoc();';
	public $formTitle = '3v4l.org<small> - Find scripts where one version differs from the others</small>';
	protected $_userinputConfig = array(
		'versions' => [
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => true,
			'options' => ['minLength' => 5, 'maxLength' => 24,
				'placeholder' => 'versions, separated by spaces',
			],
			'values' => [],
		],
		'controls' => [
			'valueType' => 'scalar',
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 2],
			'required' => false,
			'options' => ['minLength' => 5, 'maxLength' => 24,
				'placeholder' => 'versions, separated by spaces',
			],
			'values' => [],
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 3],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 9],
		],
	);
	protected $_cacheLength = '4 hours';
	public $entries;

	public function init()
	{
		global $_MULTIVIEW;

		if (isset($_MULTIVIEW[1]))
			$_POST['versions'] = explode('+', $_MULTIVIEW[1]);
		if (isset($_MULTIVIEW[2]))
			$_POST['controls'] = explode('+', $_MULTIVIEW[2]);

		$this->_userinputConfig['versions']['values'] = 
			$this->_userinputConfig['controls']['values'] = PhpShell_Version::find('"isHelper" = false', [], ['name' => false])->getSimpleList('name', 'name');

		parent::init();
	}

	public function run()
	{
		if (empty(Basic::$userinput['versions']) || count(Basic::$userinput['controls']) < 2)
			throw new PhpShell_Action_Bughunt_TooFewVersionsOrControlsSelectedException('Please select at least one version and two controls');

		$params = []; $joins=[]; $q = "input.state = 'done'";
		foreach (Basic::$userinput['versions'] as $i => $v)
		{
			$alias = 'v'.$i;
			$q .= "\nAND {$alias}.\"exitCode\" != 255 AND {$alias}.version = ?".($i>0? " AND {$alias}.output = v0.output" : "");

			array_push($joins, ['result', "{$alias}.input = input.id AND {$alias}.run = input.run", $alias]);
			array_push($params, PhpShell_Version::byName($v)->id);
		}

		foreach (Basic::$userinput['controls'] as $i => $v)
		{
			$alias = 'c'.$i;
			$q .= "\nAND {$alias}.\"exitCode\" != 255 AND {$alias}.version = ? AND {$alias}.output != v0.output".($i>0? " AND {$alias}.output = c0.output" : "");
			array_push($joins, ['result', "{$alias}.input = input.id AND {$alias}.run = input.run", $alias]);
			array_push($params, PhpShell_Version::byName($v)->id);
		}

		$this->entries = new PhpShell_BughuntSet(PhpShell_Input, $q."\n", $params, ['input.id' => true]);
		foreach ($joins as $join)
			$this->entries->addJoin(...$join);

		return parent::run();
	}
}