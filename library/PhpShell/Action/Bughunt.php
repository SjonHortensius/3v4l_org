<?php

class PhpShell_Action_Bughunt extends PhpShell_Action
{
	public $formSubmit = 'array_intersect_uassoc();';
	public $formTitle = '3v4l.org<small> - Find scripts where one version differs from the others</small>';
	protected $_userinputConfig = array(
		'versions' => [
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => true,
			'values' => [],
			'options' => ['multiple' => true],
		],
		'controls' => [
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 2],
			'required' => true,
			'values' => [],
			'options' => ['multiple' => true],
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

		$this->entries = Basic::$cache->get(__CLASS__.'::'.md5(serialize([$_POST, $_SERVER['REQUEST_URI']])), function(){
			$params = []; $joins=[]; $q = "true";
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

			$entries = new PhpShell_BughuntSet(PhpShell_Input, $q, $params, ['input.id' => true]);
			foreach ($joins as $join)
				$entries->addJoin(...$join);

			return iterator_to_array($entries->getPage(Basic::$userinput['page'], 25));
		}, 48 * 3600);

		return parent::run();
	}
}