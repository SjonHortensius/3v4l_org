<?php
// Extended by _Search
abstract class PhpShell_Action_Tagcloud extends PhpShell_Action
{
	public $words = [];
	public $max;
	public $min;

	public function generate()
	{
		$popularFunctions = PhpShell_Function::find("text != 'var_dump' AND text != 'print_r' AND popularity > 999")
			->getSimpleList('popularity', 'text');

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