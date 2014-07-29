<?php

class PhpShell_MainScriptOutput extends Basic_EntitySet
{
	protected function _query($fields = null, $groupBy = null)
	{
		if (!isset($fields))
			$fields = 'input, "exitCode", output.hash as "output$hash", output.raw as "output$raw", version.name as "version$name", version.order as "version$order"';

		return parent::_query($fields, $groupBy);
	}

	protected function _processQuery($query)
	{
		return $query ." INNER JOIN output ON output.id = result.output INNER JOIN version ON version.id = result.version";
	}

	public function getIterator()
	{
		foreach (parent::getIterator() as $id => $entity)
		{
			$entity->output = PhpShell_Output::getStub(['hash' => $entity->{'output$hash'}, 'raw' => $entity->{'output$raw'}]);
			$entity->version = PhpShell_Version::getStub(['name' => $entity->{'version$name'}, 'order' => $entity->{'version$order'}]);
			unset($entity->{'output$raw'}, $entity->{'output$hash'}, $entity->{'version$name'}, $entity->{'version$order'});

			yield $id => $entity;
		}
	}
}