<?php

class PhpShell_InputSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesVariance = false;
	public $includesPerformance = false;
	public $includesFunctionCalls = false;

	public function includeVariance()
	{
		$this->_fields []= "(SELECT 100 - (100 / GREATEST(1,(COUNT(DISTINCT output)))) FROM result WHERE input = input.id AND result.version >= 32) variance";

		$this->includesVariance = true;
		return $this;
	}

	public function includeFunctionCalls()
	{
		$this->_fields []= "(SELECT string_agg(text, ', ') FROM function WHERE id IN (SELECT function FROM \"functionCall\" WHERE input = input.id LIMIT 10)) \"functionCalls\"";

		$this->includesFunctionCalls = true;
		return $this;
	}

	public function fetchNext(): Generator
	{
		do
		{
			$result = Basic::$database->q("FETCH NEXT FROM inputCursor");
			$result->setFetchMode(PDO::FETCH_CLASS, $this->_entityType);

			$entity = $result->fetch();

			if (!$entity)
				break;

			yield $entity->id => $entity;
		}
		while (true);
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = 'input.*, input.source AS "sourceId", '. implode(', ', $this->_fields);

		return parent::_query($fields, $groupBy);
	}

	public function prepareCursor()
	{
		Basic::$database->q("DECLARE inputCursor CURSOR FOR SELECT * FROM input ORDER BY id");
	}
}
