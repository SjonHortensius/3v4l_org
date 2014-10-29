<?php

class PhpShell_Action_Script extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'script' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 0],
			'required' => true,
			/* Don't check for lengths here; /randomstring will be invalid; leading to
			 * generic 400-'unknown action' instead of 404-'unknown script' error. This is
			 * caused by us interpreting everything as a script in PhpShell_Action::resolve
			 */
//			'options' => ['minLength' => 5, 'maxLength' => 6],
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
		// Retards hammering
		if (false != strpos($_SERVER['REQUEST_URI'], '?a%5B'))
			throw new PhpShell_InvalidUrlParametersException('You sound like a bot; stop passing stupid stuff in the Request-URI', [], 404);

		if (!isset($this->bodyClass) && isset($this->_userinputConfig['tab']['values'][ $GLOBALS['_MULTIVIEW'][1] ]))
			$this->bodyClass = $GLOBALS['_MULTIVIEW'][1];

		parent::init();
	}

	public function run()
	{
		try
		{
			$this->input = PhpShell_Input::find("short = ?", [Basic::$userinput['script']])->getSingle();
		}
		catch (Basic_EntitySet_NoSingleResultException $e)
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

		if ($this->input->state == 'new')
		{
			// Attempt to retrigger the daemon
			$this->input->trigger();

			// Refresh state
			$this->input = PhpShell_Input::find("short = ?", [Basic::$userinput['script']])->getSingle();
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