<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'script';
	public $lastModified = 'now';
	public $last;
	public $popular;
	protected $_cacheLength = '45 seconds';

	public function init()
	{
		parent::init();

		$this->last = (new PhpShell_LastScriptsList)
			->getSubset('input.run > 0 AND "runQuick" ISNULL', [])
			->setOrder(['id' => false]);

		$this->popular = Basic::$cache->get('active_scripts', function(){ return [];}, 60);
	}
}