<?php

class PhpShell_Action_Embed extends PhpShell_Action_Script
{
	public $contentType = 'application/json';
	public $userinputConfig = [
		'script' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => true,
			'minLength' => 5, 'maxLength' => 7,
		],
		'tab' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 2],
			'required' => false,
			'default' => 'output',
			'values' => ['output'],
        ],
	];

	public function showTemplate(string $templateName, int $flags = 0): void
	{
		if ($templateName != 'header')
			parent::showTemplate($templateName, $flags);
	}
}
