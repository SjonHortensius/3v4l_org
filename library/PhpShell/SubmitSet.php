<?php

class PhpShell_SubmitSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesPenalties = false;

	public function includePenalties()
	{
		$this->addJoin(PhpShell_Input::class, "input.id = submit.input");

		$this->_fields []= "( 1 - (DATE_PART('epoch', NOW() - submit.created) / 86400) ) \"agePenalty\""; # end-of-day [0] -> now [1];
		$this->_fields []= "( 32*submit.count + (5*penalty) * (CASE WHEN \"runQuick\" IS NULL THEN 1 ELSE 0.05 END) ) \"weightPenalty\""; # no penalty + quick [12.8] -> [3428] (1100 avg penalty)
		$this->_fields []= "( CASE WHEN state IN ('busy', 'new') THEN 5 WHEN state IN ('abusive') THEN 9 ELSE 1 END ) \"busyPenalty\""; # normal submits [0.3] - abusive[3] - busy/new [5]

		$this->includesPenalties = true;
		return $this;
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = "submit.*, ". implode(",\n", $this->_fields);

		return parent::_query($fields, $groupBy);
	}

	public function getAggregate(string $fields = "COUNT(*)", string $groupBy = null, array $order = []): Basic_DatabaseQuery
	{
		$set = clone $this;
		$set->_order = $order;

		$f = [$fields];
		foreach ($this->_fields as $field)
			$f []= 'AVG'. $field;

		return $set->_query(implode(",\n", $f), $groupBy);
	}
}
