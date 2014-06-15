<?php

class PhpShell_Operation extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input,
	];
	protected static $_numerical = ['count'];
}