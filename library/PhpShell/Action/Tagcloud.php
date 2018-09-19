<?php
// Extended by _Search
abstract class PhpShell_Action_Tagcloud extends PhpShell_Action
{
	public $words = [];
	public $max;
	public $min;

	public function generate()
	{
		$popularFunctions = Basic::$cache->get(__METHOD__, function(){
			return array_slice(iterator_to_array(
				PhpShell_FunctionCall::find("function NOT IN ('var_dump', 'print_r')")
					->getAggregate("COUNT(*), function", "function", ["COUNT(*)" => false])
					->fetchArray("count", "function")
			), 0, 150);
		}, 86400);

		foreach ($popularFunctions as $text => $size)
		{
			array_push($this->words, ['text' => $text, 'size' => $size]);

			if (!isset($this->min, $this->max))
				$this->max = $this->min = $size;

			$this->max = max($this->max, $size);
			$this->min = min($this->min, $size);
		}
	}
}