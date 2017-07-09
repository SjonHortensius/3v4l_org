<?php

class PhpShell_InputSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesVariance = false;
	public $includesPerformance = false;
	public $includesOperations = false;

	public function includeVariance()
	{
		// Using Current produces incomplete variance and ~time but is ~4 times faster
		$this->addJoin(PhpShell_ResultCurrent::class, "result_current.input = input.id AND result_current.version >= 32");
		$this->_fields []= "(COUNT(DISTINCT result_current.output)-1) * 100 / COUNT(result_current.output) variance";

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
