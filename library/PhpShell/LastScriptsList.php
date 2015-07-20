<?php
class PhpShell_LastScriptsList extends Basic_EntitySet
{
	protected function _query($fields = null, $groupBy = "input.id")
	{
		// sourceId prevents $this->prevResult->source->id from fetching source.* from input
		$fields = 'input.*, AVG("userTime") "userTime",
			AVG("systemTime") "systemTime",
			AVG("maxMemory") "maxMemory",
			source AS "sourceId",
			COUNT(DISTINCT output) * 100 / COUNT(output) variance';

		return parent::_query($fields, $groupBy);
	}

	protected function _processQuery($query)
	{
		$helpers = Basic::$cache->get(__CLASS__.'::helperIds', function(){
			return array_keys(PhpShell_Version::find('"isHelper"')->getSimpleList());
		});

		return $query ."\nJOIN result ON (result.input = input.id AND result.version NOT IN(".implode(",", $helpers)."))\n";
	}
}
