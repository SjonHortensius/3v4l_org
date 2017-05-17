<?php
class PhpShell_LastScriptsList extends Basic_EntitySet
{
	public function __construct()
	{
		parent::__construct(PhpShell_Input::class);

		$this->addJoin(PhpShell_Result, "result.input = input.id AND result.version >= 32");
	}

	protected function _query($fields = "*", $groupBy = null): Basic_DatabaseQuery
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
