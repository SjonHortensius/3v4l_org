<?php
class PhpShell_BughuntSet extends Basic_EntitySet
{
	protected $_joins = [];

	protected function _query($fields = "*", $groupBy = null)
	{
		return parent::_query("input.*", $groupBy);
	}

	//FIXME; move to Basic_EntitySet?
	public function addJoin($table, $condition, $alias = null)
	{
		if (!isset($alias))
			$alias = $table;

		$this->_joins[ $alias ] = ['table' => $table, 'condition' => $condition];
	}

	protected function _processQuery($query)
	{
		foreach ($this->_joins as $alias => $join)
			$query .= "\nJOIN {$join['table']} {$alias} ON ({$join['condition']})";

		return $query;
	}
}
