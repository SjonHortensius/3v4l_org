<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'script';
	public $lastModified = 'now';
	public $last;
	public $popular;
	protected $_cacheLength = '45 seconds';
	const ACTIVE_SCRIPTS = 9;

	public function init(): void
	{
		parent::init();

		$this->last = PhpShell_Input::find()
			->getSubset('input.run > 0 AND "runQuick" ISNULL', [])
			->setOrder(['id' => false]);

		$this->popular = Basic::$cache->get('Hits:popular', function(){ return []; }, 60);
	}
}