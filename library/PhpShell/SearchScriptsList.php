<?php

class PhpShell_SearchScriptsList extends PhpShell_LastScriptsList
{
	protected function _query($fields = "*", $groupBy = null)
	{
		$fields = 'SUM(operations.count) count,
			input.*,
			AVG("userTime") "userTime",
			AVG("systemTime") "systemTime",
			AVG("maxMemory") "maxMemory",
			COUNT(DISTINCT output) * 100 / COUNT(output) variance';

		return Basic_EntitySet::_query($fields, "input.id");
	}
}