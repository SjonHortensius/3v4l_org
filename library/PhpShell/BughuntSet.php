<?php
class PhpShell_BughuntSet extends Basic_EntitySet
{
	protected function _query($fields = "*", $groupBy = null)
	{
		return parent::_query("input.*", $groupBy);
	}
}
