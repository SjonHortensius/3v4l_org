<?php

class PhpShell_MainScriptOutput extends Basic_EntitySet
{
	protected function _query($fields = null, $groupBy = null)
	{
		if (!isset($fields))
			$fields = 'input, output, version as version_name, "exitCode", raw, version.order';

		return parent::_query($fields, $groupBy);
	}

	protected function _processQuery($query)
	{
		return $query ." INNER JOIN output ON output.hash = result.output INNER JOIN version ON version.name = result.version";
	}
}
