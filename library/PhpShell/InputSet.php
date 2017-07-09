<?php

class PhpShell_InputSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesVariance = false;
	public $includesPerformance = false;
	public $includesOperations = false;

	public function includeVariance()
	{
		$this->addJoin(PhpShell_Result::class, "result.input = input.id AND result.version >= 32");
		$this->_fields []= "(COUNT(DISTINCT result.output)-1) * 100 / COUNT(result.output) variance";

		$this->includesVariance = true;
		return $this;
	}

	public function includePerformance()
	{
		$this->includeVariance();
		$this->_fields []= 'AVG("userTime") "userTime", AVG("systemTime") "systemTime", AVG("maxMemory") "maxMemory"';

		$this->includesPerformance = true;
		return $this;
	}

	public function includeOperations()
	{
		$this->_fields []= "(SELECT string_agg(operand, ', ') FROM (SELECT operand FROM operations WHERE input = id AND operation IN('FETCH_CLASS','INIT_FCALL') ORDER BY count DESC LIMIT 10) AS popularOperations) operations";

		$this->includesOperations = true;
		return $this;
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = 'input.*, source AS "sourceId", '. implode(', ', $this->_fields);

		return parent::_query($fields, "input.id");
	}
}
