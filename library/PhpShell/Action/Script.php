<?php

class PhpShell_Action_Script extends PhpShell_Action
{
	public $userinputConfig = [
		'script' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'REQUEST', 'key' => 0],
			'required' => true,
			/* Don't check for lengths here; /randomstring will be invalid; leading to
			 * generic 400-'unknown action' instead of 404-'unknown script' error. This is
			 * caused by us interpreting everything as a script in PhpShell_Action::resolve
			 */
//			'minLength' => 5, 'maxLength' => 6,
		],
		'tab' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => false,
			'default' => 'output',
			'values' => [
				'output' => 'Output',
				'perf' => 'Performance',
				'vld' => 'VLD',
				'refs' => 'References',
				'rfc' => 'Branches',
			]
		],
	];
	/** @var $input PhpShell_Input */
	public $input;
	public $showTab = [
		'output' => true,
		'perf' => true,
	];
	public $notifyTab = [];
	public $bodyClass = 'new script';
	public $quickVersionList;

	public function init(): void
	{
		$this->bodyClass .= ' '. Basic::$userinput['tab'];
		$this->title = Basic::$userinput['tab'] .' for '. Basic::$userinput['script'];

		// needed because we serve different content on the same URI, which browsers may cache
		if ('.json' == strpbrk(Basic::$userinput['script'], '.') && 'application/json' == $_SERVER['HTTP_ACCEPT']??'')
		{
			// Discourage public /script.json usage - they should use only Accept: for that
			Basic::$template->scriptSkipCode = true;
			Basic::$userinput->script->setValue(substr(Basic::$userinput['script'], 0, -5));
		}

		// Rebecca, April 1st
		if (in_array(Basic::$userinput['script'], ['1bYJv', 'p32ZU']))
			$this->cspDirectives['frame-src'] = ['https://www.youtube.com'];

		if (in_array(Basic::$userinput['script'], ['aV2i2', 'XD6qI']) && 'Blackboard Safeassign' === $_SERVER['HTTP_USER_AGENT'])
			die(http_response_code(429));

		// we want to possibly truncate the header if _handleLastModified() exits
		ob_start();

		parent::init();
	}

	public function run(): void
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

		if (!in_array($this->input->state, ['busy', 'new']))
		{
			$this->_lastModified = $this->input->getLastModified();
			$this->_cacheLength = '5 minutes';
		}

		// Rerun caching logic now that we have input.lastModified
		$this->_handleLastModified();

		try
		{
			// Retrieve the VLD so we update the operationCount
			if (Basic::$config->PRODUCTION_MODE && !isset($this->input->operationCount))
				$this->input->getVld();
		}
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			//ignore
		}

		$output = ['main' => [], 'rfc' => []];
		foreach ($this->input->getResults() as $result)
		{
			if ($result->version->isHelper)
				continue;

			if (in_array(substr($result->version->name, 0, 3), ['rfc', 'git']))
			{
				$this->showTab['rfc'] = true;
				$output['rfc'] []= $result->output->id;
			} else
				$output['main'] []= $result->output->id;
		}

		if (count(array_diff($output['rfc'], $output['main'])) > 1)
			$this->notifyTab[ 'rfc'==Basic::$userinput['tab']?'output':'rfc' ] = true;

		$this->showTab['vld'] = isset($this->input->operationCount);
		$this->showTab['refs'] = $this->showTab['vld'] && count($this->input->getRelated(PhpShell_FunctionCall::class)) > 0;

		if (!isset($this->showTab[ Basic::$userinput['tab'] ]) || false === $this->showTab[ Basic::$userinput['tab'] ])
			throw new PhpShell_Action_Script_TabHasNoContentException("This script has no output for requested tab `%s`", [Basic::$userinput['tab']], 404);

		if ('done' == $this->input->state)
			$this->input->logHit();

		$this->showTemplate(Basic::$userinput['action'], Basic_Template::UNBUFFERED);
	}

	protected function _handleLastModified(): void
	{
		// truncate header if we're gonna send a 304 - required because we call _handleLastModified from run() which is unsupported
		register_shutdown_function(function() { if (304 == http_response_code()) ob_end_clean();});

		parent::_handleLastModified();

		// if we didn't exit, end the output buffer
		ob_end_flush();
	}
}