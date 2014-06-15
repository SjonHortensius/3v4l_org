<?php

class PhpShell_Action_Script extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'script' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 0],
			'required' => true,
			'options' => ['minLength' => 5, 'maxLength' => 5],
		],
		'tab' => [
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'required' => false,
			'default' => 'output',
			'values' => [
				'output' => 'Output',
				'vld' => 'VLD opcodes',
				'perf' => 'Performance',
				'refs' => 'References',
//				'rel' => 'Related',
				'segfault' => 'Segmentation fault',
				'analyze' => 'HHVM analyze',
				'hhvm' => null, #legacy
			]
		],
	);
	public $cacheLength = '5 minutes';
	public $code;
	public $input;
	public $showTab = [];

	public function init()
	{
		parent::init();

		// Bug? A hammering user will GET /new which doesn't exist and results in 404 instead of 503
		if ('new' == Basic::$userinput['script'])
			return $this->getError(503);

		try
		{
			$this->input = PhpShell_Input::get(Basic::$userinput['script']);
		}
		catch (Basic_Entity_NotFoundException $e)
		{
			$this->input = PhpShell_Input::find('alias = ?', [Basic::$userinput['script']])->getSingle();
			Basic::$controller->redirect($this->input->short. ('output' != Basic::$userinput['tab'] ? '/'. Basic::$userinput['tab'] : ''), true);
		}

		// legacy, redirect to /script
		if ('hhvm' == Basic::$userinput['tab'])
			Basic::$controller->redirect($this->input->short, true);

		if (!isset($this->user))
		{
			$this->lastModified = $this->input->getLastModified();
			parent::_handleLastModified();
		}
	}

	public function run()
	{
		if ($this->input->state == 'new')
		{
			// Attempt to retrigger the daemon
			$this->input->trigger();

			// Refresh state
			$this->input = PhpShell_Input::get(Basic::$userinput['script']);
		}

		$this->code = $this->input->getCode();

		if (!isset($this->input->operationCount))
			$this->input->updateOperations();

		$this->showTab = [
			'vld' => isset($this->input->operationCount),
			'refs' => count($this->input->getRefs()) > 0,
			'segfault' => count($this->input->getSegfault()) > 0,
			'analyze' => count($this->input->getAnalyze()) > 0,
		];


/*
switch ($this->input->state)
{
	case 'misbehaving':
	case 'abusive':
?>
	<div class="alert error">
		<h2>Abusive script</h2>
		<p>This script was stopped while abusing our resources</p>
	</div>
<? break;
	case 'verbose':
?>
	<div class="alert warning">
		<h2>Verbose script</h2>
		<p>This script was stopped because it was generating too much output</p>
	</div>
<? break;
}

 */

		$this->showTemplate('script');
	}

	public static function sortAnalyzeByLine(&$messages)
	{
		usort($messages, function($a, $b){
			return $a[1]->c1[1] - $b[1]->c1[1];
		});
	}
}