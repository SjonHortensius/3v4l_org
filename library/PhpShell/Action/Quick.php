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
	public $bodyClass = 'script';
	public $input;
	public $output;
	public $result;

	public function init()
	{
		//FIXME: support multiple=multiple?
		$this->_userinputConfig['version']['values'] = PhpShell_Version::find("eol>NOW() OR (\"isHelper\" AND NOT name IN('segfault', 'hhvm-bytecode'))", [], ['version.order' => false])->getSimpleList('name', 'name');

		if (isset($GLOBALS['_MULTIVIEW'][1]))
			$this->_userinputConfig['version']['default'] = $GLOBALS['_MULTIVIEW'][1];

		parent::init();
	}

	public function run()
	{
		$code = PhpShell_Input::clean(Basic::$userinput['code']);
		$hash = PhpShell_Input::getHash($code);
		$version = PhpShell_Version::byName(Basic::$userinput['version']);
/*
		$penalty = Basic::$database->query("
			SELECT SUM((86400-date_part('epoch', now()-submit.created)) * submit.count * (1+(penalty/128))) p
			FROM submit
			JOIN input ON (input.id = submit.input)
			WHERE ip = ? AND now() - submit.created < '1 day' AND NOT \"runQuick\" ISNULL", [ $_SERVER['REMOTE_ADDR'] ])->fetchArray()[0]['p'];

		usleep($penalty);
*/
		try
		{
			$this->input = PhpShell_Input::byHash($hash);

			if ($this->input->state == "busy")
				throw new PhpShell_ScriptAlreadyRunningException('The server is already processing your code, please wait for it to finish.');

			// Prevent partially running a full script
			if (0 == $this->input->getResult($version)->getCount(null, true))
				$this->input->trigger($version);
		}
		// No results from ::byHash
		catch (Basic_EntitySet_NoSingleResultException $e)
		{
			$this->input = PhpShell_Input::create([
				'code' => $code,
				'source' => $source,
				'title' => Basic::$userinput['title'],
				'runQuick' => $version,
			]);
		}

		PhpShell_Submit::create(['input' => $this->input, 'ip' => $_SERVER['REMOTE_ADDR']]);

		$this->input->waitUntilNoLonger('busy');

		$this->result = $this->input->getResult($version)->getSingle();
		$this->output = $this->result->output->getRaw($this->input, Basic::$userinput['version']);

		return parent::run();
	}

	// For people without javascript
	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if (isset($_POST['versions']) && $_POST['versions'] == '*')
			return 'new';
	}
}