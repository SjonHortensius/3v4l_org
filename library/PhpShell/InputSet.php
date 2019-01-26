<?php

class PhpShell_InputSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesVariance = false;
	public $includesPerformance = false;
	public $includesFunctionCalls = false;

	public function includeVariance()
	{
		$this->_fields []= "(SELECT (COUNT(DISTINCT output)-1) * 100 / COUNT(output) FROM result WHERE input = input.id AND result.version >= 32) variance";

		$this->includesVariance = true;
		return $this;
	}

	public function includeFunctionCalls()
	{
		$this->_fields []= "(SELECT string_agg(text, ', ') FROM function WHERE id IN (SELECT function FROM \"functionCall\" WHERE input = input.id LIMIT 10)) \"functionCalls\"";

		$this->includesFunctionCalls = true;
		return $this;
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = 'input.*, input.source AS "sourceId", '. implode(', ', $this->_fields);

		return parent::_query($fields, $groupBy);
	}
}
