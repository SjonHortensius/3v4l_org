<?php
class PhpShell_ScriptsList extends Basic_EntitySet
{
	protected function _query($fields = null, $groupBy = "input.id")
	{
		if (!isset($fields))
			$fields = 'input.*, AVG("userTime") "userTime",
				AVG("systemTime") "systemTime",
				AVG("maxMemory") "maxMemory",
				COUNT(DISTINCT output) * 100 / COUNT(output) variance';

		return parent::_query($fields, $groupBy);
	}

	protected function _processQuery($query)
	{
		return $query ." JOIN result ON (result.input = input.id) JOIN version ON (version.id = result.version)";
	}
}