<?php
/* Optimized EntitySet which fetches partial related entities in a single query */
class PhpShell_MainScriptOutput extends Basic_EntitySet
{
	protected function _query($fields = "*", $groupBy = null)
	{
		$fields = 'input, "exitCode",
			output.hash as "output$hash", output.raw as "output$raw",
			version.name as "version$name", version.order as "version$order", version.released as "version$released"
		';

		return parent::_query($fields, $groupBy);
	}

	public function getIterator()
	{
		foreach (parent::getIterator() as $id => $entity)
		{
			$entity->output = PhpShell_Output::getStub([
				'hash' => $entity->{'output$hash'},
				'raw' => $entity->{'output$raw'}
			]);
			$entity->version = PhpShell_Version::getStub([
				'name' => $entity->{'version$name'},
				'order' => $entity->{'version$order'},
				'released' => $entity->{'version$released'}
			]);

			unset($entity->{'output$raw'}, $entity->{'output$hash'}, $entity->{'version$name'}, $entity->{'version$order'}, $entity->{'version$released'});

			yield $id => $entity;
		}
	}
}