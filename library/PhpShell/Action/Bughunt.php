<?php

class PhpShell_Action_Bughunt extends PhpShell_Action
{
	public $formSubmit = 'array_intersect_uassoc();';
	public $formTitle = '3v4l.org<small> - Find scripts where one version differs from the others</small>';
	protected $_userinputConfig = array(
		'versions' => [
			'description' => 'Select one version you want to focus on when comparing',
//			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => true,
			'values' => [],
			'options' => ['multiple' => true],
		],
		'controls' => [
			'description' => 'Select two versions to compare against. Output from all controls must match, '.
				'this eliminates scripts that have a high variance (caused by time based or random output)',
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
	public $blackList = ['lcg_value', 'rand', 'mt_rand', 'microtime', 'array_rand', 'disk_free_space', 'memory_get_usage', 'shuffle', 'timezone_version_get', 'random_int', 'uniqid'];

	public function init()
	{
		global $_MULTIVIEW;

		if (isset($_MULTIVIEW[1]))
			$_POST['versions'] = explode('+', $_MULTIVIEW[1]);
		if (isset($_MULTIVIEW[2]))
			$_POST['controls'] = explode('+', $_MULTIVIEW[2]);

		$this->_userinputConfig['versions']['values'] = $this->_userinputConfig['controls']['values'] =
			PhpShell_Version::find('"isHelper" = false AND eol>now() and now()-released < \'1 year\'', [], ['version.order' => false])->getSimpleList('name', 'name');

		parent::init();
	}

	public function run()
	{
		if (1 != count(Basic::$userinput['versions']) || 2 != count(Basic::$userinput['controls']))
			throw new PhpShell_Action_Bughunt_TooFewVersionsOrControlsSelectedException('Please select exactly one version and two controls');

		$params = []; $joins=[]; $q = "true";
		foreach (Basic::$userinput['versions'] as $i => $v)
		{
			$alias = 'v'.$i;
			$q .= "\nAND {$alias}.version = ?".($i>0? " AND {$alias}.output = v0.output" : "");

			array_push($joins, ['result_current', "{$alias}.input = input.id AND {$alias}.run = input.run", $alias]);
			array_push($params, PhpShell_Version::byName($v)->id);
		}

		foreach (Basic::$userinput['controls'] as $i => $v)
		{
			$alias = 'c'.$i;
			$q .= "\nAND {$alias}.version = ? AND {$alias}.output != v0.output".($i>0? " AND {$alias}.output = c0.output" : "");
			array_push($joins, ['result_current', "{$alias}.input = input.id AND {$alias}.run = input.run", $alias]);
			array_push($params, PhpShell_Version::byName($v)->id);
		}

		$q .= "\nAND input.id NOT IN (SELECT input FROM bughunt_blacklist)\n";

		$this->entries = new PhpShell_BughuntSet(PhpShell_Input, $q, $params, ['input.id' => true]);
		foreach ($joins as $join)
			$this->entries->addJoin(...$join);

		return parent::run();
	}
}