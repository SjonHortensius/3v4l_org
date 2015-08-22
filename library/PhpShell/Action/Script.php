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
				'bytecode' => 'HHVM bytecode',
				'hhvm' => null, #legacy
				'rfc' => 'RFC branches',
			]
		],
	);
	public $code;
	public $input;
	public $showTab = [];
	public $bodyClass = 'output script';

	public function init()
	{
		if (isset($this->_userinputConfig['tab']['values'][ $GLOBALS['_MULTIVIEW'][1] ]))
			$this->bodyClass = $GLOBALS['_MULTIVIEW'][1]. ' script';

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

		if ($this->input->state == 'new')
		{
			// Attempt to retrigger the daemon
			$this->input->trigger();

			// Refresh state
			$this->input = PhpShell_Input::find("id = ?", [$this->input->id])->getSingle();
		}

		$this->code = $this->input->getCode();

		if (!isset($this->input->operationCount))
			$this->input->updateOperations();

		$this->showTab = [
			'vld' =>		isset($this->input->operationCount),
			'refs' =>		!empty(iterator_to_array($this->input->getRefs())),
			'segfault' =>	!empty(iterator_to_array($this->input->getSegfault())),
			'bytecode' =>	!empty(iterator_to_array($this->input->getBytecode())),
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