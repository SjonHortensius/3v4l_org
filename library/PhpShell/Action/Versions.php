<?php

class PhpShell_Action_Versions extends PhpShell_Action
{
	public $versions;

	public function run()
	{
		$this->versions = PhpShell_Version::find("NOT \"isHelper\"", [], ['"order"' => false]);

		parent::run();
	}
}
