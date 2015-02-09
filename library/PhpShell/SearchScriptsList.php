<?php

class PhpShell_SearchScriptsList extends PhpShell_LastScriptsList
{
	protected function _query($fields = null, $groupBy = "input.id")
	{
		if (!isset($fields))
			$fields = 'SUM(DISTINCT operations.count) count, input.*, AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance';

		return Basic_EntitySet::_query($fields, $groupBy);
	}

	protected function _processQuery($query)
	{
		return parent::_processQuery($query) ."JOIN operations ON (operations.input = input.id)\n";
	}
}
