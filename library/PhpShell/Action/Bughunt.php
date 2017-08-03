<?php

class PhpShell_Action_Bughunt extends PhpShell_Action
{
	public $title = 'Find scripts where one version differs from the others';
	public $formSubmit = 'array_intersect_uassoc();';
	public $userinputConfig = [
		'versions' => [
			'description' => 'Select one version you want to focus on when comparing',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => true,
			'values' => [],
			'options' => ['multiple' => true],
		],
		'controls' => [
			'description' => 'Select two versions to compare against. Output from all controls must match, '.
				'this eliminates scripts that have a high variance (caused by time based or random output)',
//			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'required' => true,
			'values' => [],
			'options' => ['multiple' => true],
		],
		'page' => [
			'valueType' => 'integer',
			'source' => ['superglobal' => 'REQUEST', 'key' => 3],
			'default' => 1,
			'options' => ['minValue' => 1, 'maxValue' => 99],
		],
	];
	protected $_cacheLength = '4 hours';
	public $entries;

	public function init(): void
	{
		$versions = iterator_to_array(PhpShell_Version::find('("isHelper" = false OR name LIKE \'rfc-%\') AND eol>now() and now()-released < \'1 year\'', [])
			->setOrder(['version.order' => false])
			->getSimpleList('name', 'name'));

		Basic::$userinput->versions->values = $versions;
		Basic::$userinput->controls->values = $versions;

		if (isset($_REQUEST[1]))
			Basic::$userinput->versions->setValue(explode('+', $_REQUEST[1]));

		if (isset($_REQUEST[2]))
			Basic::$userinput->controls->setValue(explode('+', $_REQUEST[2]));

		parent::init();
	}

	public function run(): void
	{
		if (1 != count(Basic::$userinput['versions']) || 2 != count(Basic::$userinput['controls']))
			throw new PhpShell_Action_Bughunt_TooFewVersionsOrControlsSelectedException('Please select exactly one version and two controls');

		$this->entries = PhpShell_Input::find()
			->setOrder(['input.id' => true])
			->includeOperations();

		foreach (['versions', 'controls'] as $idx)
			foreach (Basic::$userinput[$idx] as $i => $v)
			{
				$alias = $idx[0].$i;

				$this->entries = $this->entries->addJoin(PhpShell_ResultBughunt::class, "{$alias}.input = input.id AND {$alias}.run = input.run", $alias, "INNER", false)
					->getSubset("\n{$alias}.version = ?", [PhpShell_Version::byName($v)->id]);

				if ('controls' == $idx)
					$this->entries = $this->entries->getSubset("{$alias}.output != v0.output");

				if ($i > 0)
					$this->entries = $this->entries->getSubset("{$alias}.output = {$idx[0]}0.output");
			}

		parent::run();
	}
}