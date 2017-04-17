<?php
class PhpShell_LastScriptsList extends Basic_EntitySet
{
	protected function _query($fields = "*", $groupBy = null)
	{
		// sourceId prevents $this->prevResult->source->id from fetching source.* from input
		$fields = 'input.*, AVG("userTime") "userTime",
			AVG("systemTime") "systemTime",
			AVG("maxMemory") "maxMemory",
			source AS "sourceId",
			(COUNT(DISTINCT output)-1) * 100 / COUNT(output) variance';

		return parent::_query($fields, "input.id");
	}
}
