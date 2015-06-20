<?php
// Extended by _Search
class PhpShell_Action_Tagcloud extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'ip' => [
			'valueType' => 'scalar',
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'regexp' => '~^(%|[0-9.]+)$~',
			'default' => '%',
		],
	);
	public $words = [];
	public $max;
	public $min;

	public function init()
	{
//		$this->_userinputConfig['ip']['default'] = $_SERVER['REMOTE_ADDR'];

		parent::init();
	}

	public function generate()
	{
		$functions = Basic::$cache->get(__CLASS__.'::popularOperands', function(){
			return iterator_to_array(Basic::$database->query("
	SELECT operand AS text, SUM(operations.count) AS size
	FROM operations
	WHERE operation = 'DO_FCALL' AND operand NOT IN ('var_dump', 'print_r')
	GROUP BY operand
	ORDER BY size DESC
	LIMIT 150"));
		}, 86400);

		foreach ($functions as $f)
		{
			array_push($this->words, ['text' => $f['text'], 'size' => $f['size']]);

			if (!isset($this->min, $this->max))
				$this->max = $this->min = $f['size'];

			$this->max = max($this->max, $f['size']);
			$this->min = min($this->min, $f['size']);
		}
	}

	public function run()
	{
//		$this->generate();

		return parent::run();
	}
}
