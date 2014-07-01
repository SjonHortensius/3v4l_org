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
				'perf' => 'Performance',
				'vld' => 'VLD opcodes',
				'refs' => 'References',
//				'rel' => 'Related',
				'segfault' => 'Segmentation fault',
				'analyze' => 'HHVM analyze',
				'bytecode' => 'HHVM Bytecode',
				'hhvm' => null, #legacy
			]
		],
	);
	public $code;
	public $input;
	public $showTab = [];

	public function init()
	{
		parent::init();

		// Bug? A hammering user will GET /new which doesn't exist and results in 404 instead of 503
		if ('new' == Basic::$userinput['script'])
			throw new PhpShell_RateLimitingReachedException('Please refrain from hammering this service. You are limited to 5 POST requests per minute', [], 503);

		try
		{
			$this->input = PhpShell_Input::get(Basic::$userinput['script']);
		}
		catch (Basic_Entity_NotFoundException $e)
		{
			try
			{
				$this->input = PhpShell_Input::find('alias = ?', [Basic::$userinput['script']])->getSingle();
			}
			catch (Basic_EntitySet_NoSingleResultException $e)
			{
				throw new PhpShell_NotFoundException('You requested a non-existing resource', [], 404);
			}

			Basic::$controller->redirect($this->input->short. ('output' != Basic::$userinput['tab'] ? '/'. Basic::$userinput['tab'] : ''), true);
		}

		// legacy, redirect to /script
		if ('hhvm' == Basic::$userinput['tab'])
			Basic::$controller->redirect($this->input->short, true);

		if (!isset($this->user))
		{
			if (in_array($this->input->state, ['busy', 'new']))
			{
				$this->lastModified = time();
				$this->cacheLength = 0;
			}
			else
			{
				$this->lastModified = $this->input->getLastModified();
				$this->cacheLength = '5 minutes';
			}

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
			'bytecode' => count($this->input->getBytecode()) > 0,
		];

		if (false === $this->showTab[ Basic::$userinput['tab'] ])
			http_response_code(404);

		parent::run();
	}

	public static function sortAnalyzeByLine(&$messages)
	{
		usort($messages, function($a, $b){
			return $a[1]->c1[1] - $b[1]->c1[1];
		});
	}
}
