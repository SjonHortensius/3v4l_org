<?php

class PhpShell_Action_Script extends PhpShell_Action
{
	public $userinputConfig = array(
		'script' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'REQUEST', 'key' => 0],
			'required' => true,
			/* Don't check for lengths here; /randomstring will be invalid; leading to
			 * generic 400-'unknown action' instead of 404-'unknown script' error. This is
			 * caused by us interpreting everything as a script in PhpShell_Action::resolve
			 */
//			'options' => ['minLength' => 5, 'maxLength' => 6],
		],
		'tab' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'required' => false,
			'default' => 'output',
			'values' => [
				'output' => 'Output',
				'perf' => 'Performance',
				'vld' => 'VLD opcodes',
				'refs' => 'References',
//				'rel' => 'Related',
				'segfault' => 'Segmentation fault',
				'bytecode' => 'HHVM bytecode',
				'hhvm' => null, #legacy
				'rfc' => 'RFC branches',
			]
		],
	);
	public $input;
	public $showTab = [];
	public $bodyClass = 'script';
	public $quickVersionList;

	public function init()
	{
		$this->bodyClass .= ' '. Basic::$userinput['tab'];
		$this->title = Basic::$userinput['tab'] .' for '. Basic::$userinput['script'];

		# needed because we used to serve different content on the same URI, which browsers may cache
		if (false !== strpos(Basic::$userinput['script'], '.json'))
		{
			$this->contentType = 'application/json';
			//Basic::$userinput['script']->setValue(substr(Basic::$userinput['script'], strlen(Basic::$userinput['script'])-4));
			$_REQUEST[0] = str_replace('.json', '', Basic::$userinput['script']);
		}

		// Rebecca, April 1st
		if (in_array(Basic::$userinput['script'], ['1bYJv', 'p32ZU']))
			array_push($this->cspDirectives['child-src'], 'https://www.youtube.com');

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

		if (!in_array($this->input->state, ['busy', 'new']))
		{
			$this->_lastModified = $this->input->getLastModified();
			$this->_cacheLength = '5 minutes';
		}

		// Rerun caching logic now that we have input.lastModified
		parent::_handleLastModified();

		// Attempt to retrigger the daemon
		if ($this->input->state == 'new')
			$this->input->trigger();

		if (!isset($this->input->runQuick) && (!isset($this->input->operationCount) || Basic::$config->PRODUCTION_MODE && mt_rand(0,9)<1))
			$this->input->updateOperations();

		$this->showTab = array_fill_keys(array_keys($this->userinputConfig['tab']['values']), true);
		$this->showTab['vld'] = (isset($this->input->operationCount) && $this->input->operationCount > 0);
		$this->showTab['segfault'] = (count($this->input->getSegfault()) > 0);
		$this->showTab['bytecode'] = (count($this->input->getBytecode()) > 0);
		$this->showTab['refs'] = (count(iterator_to_array($this->input->getRefs())) > 0);

		unset($this->showTab['hhvm']);

		if (false === $this->showTab[ Basic::$userinput['tab'] ])
			throw new PhpShell_Action_Script_TabHasNoContentException("This script has no output for requested tab `%s`", [Basic::$userinput['tab']], 404);

		parent::run();
	}

	public static function sortAnalyzeByLine(&$messages)
	{
		usort($messages, function($a, $b){
			return $a[1]->c1[1] - $b[1]->c1[1];
		});
	}
}