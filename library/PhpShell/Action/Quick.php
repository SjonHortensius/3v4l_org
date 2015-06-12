<?php

class PhpShell_Action_Quick extends PhpShell_Action
{
	public $formSubmit = '$ php -a';
	public $formTitle = '3v4l.org<small> - online PHP & HHVM shell, run code in 150+ different versions!</small>';
	protected $_userinputConfig = array(
		'title' => [
			'inputType' => 'hidden',
		],
		'code' => [
			'valueType' => 'scalar',
			'inputType' => 'textarea',
			'regexp' => '~<\?~',
			'default' => "<?php\n\n",
			'required' => true,
		],
		'version' => [
			'inputType' => 'select',
			'values' => []
		],
	);
	public $result;
	public $input;

	public function init()
	{
		$this->_userinputConfig['version']['values'] = PhpShell_Version::find('"isHelper" = false', [], ['name' => false])->getSimpleList('name', 'name');

		if (isset($GLOBALS['_MULTIVIEW'][1]))
			$this->_userinputConfig['version']['default'] = $GLOBALS['_MULTIVIEW'][1];

		parent::init();
	}

	public function run()
	{
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);
		$version = PhpShell_Version::byName(Basic::$userinput['version']);

		try
		{
			$this->input = PhpShell_Input::byHash($hash);

			if ($this->input->state == "busy")
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			if (0 == $this->input->getResult($version)->getCount(null, true))
				$this->input->trigger($version);
		}
		// No results from ::byHash
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			$this->input = PhpShell_Input::create($code, ['quickVersion' => $version]);
		}

		PhpShell_Submit::create(['input' => $this->input, 'ip' => $_SERVER['REMOTE_ADDR']]);

		$this->output = $this->input->getResult($version)->getSingle()->output->getRaw($this->input, Basic::$userinput['version']);

		return parent::run();
	}

	// For people without javascript
	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if (isset($_POST['versions']) && $_POST['versions'] == '*')
			return 'new';
	}
}