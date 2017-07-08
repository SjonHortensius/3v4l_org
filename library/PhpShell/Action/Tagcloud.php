<?php
// Extended by _Search
abstract class PhpShell_Action_Tagcloud extends PhpShell_Action
{
	public $userinputConfig = array(
		'ip' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'regexp' => '~^(%|[0-9.]+)$~',
			'default' => '%',
		],
	);
	public $words = [];
	public $max;
	public $min;

	public function init()
	{
//		$this->userinputConfig['ip']['default'] = $_SERVER['REMOTE_ADDR'];

		parent::init();
	}

	public function generate()
	{
		foreach (Basic::$database->query("SELECT * FROM search_popularOperands")->fetchArray('size', 'text') as $text => $size)
		{
			array_push($this->words, ['text' => $text, 'size' => $size]);

			if (!isset($this->min, $this->max))
				$this->max = $this->min = $size;

			$this->max = max($this->max, $size);
			$this->min = min($this->min, $size);
		}
	}
}