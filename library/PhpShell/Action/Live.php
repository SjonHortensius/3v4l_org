<?php
class PhpShell_Action_Live extends PhpShell_Action {
	public $title = 'interactive php shell (beta)';

	public function init(): void
	{
		# https://bugs.chromium.org/p/chromium/issues/detail?id=686369
		$this->cspDirectives['script-src'] []= "'unsafe-eval'";

		parent::init();
	}
}
