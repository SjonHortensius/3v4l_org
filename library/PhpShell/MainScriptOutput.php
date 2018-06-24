<?php
/* Optimized EntitySet which fetches partial related entities in a single query */
class PhpShell_MainScriptOutput extends Basic_EntitySet
{
	public function __construct()
	{
		parent::__construct(PhpShell_Result::class);

		$this->addJoin(PhpShell_Output::class, "output.id = result.output")
			->addJoin(PhpShell_Version::class, "version.id = result.version");
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		$fields = 'result.input, result."exitCode",
			output.hash as "output$hash", output.raw as "output$raw",
			version.name as "version$name", version.order as "version$order", version.released as "version$released"';

		return parent::_query($fields, $groupBy);
	}

	public function getIterator(string $fields = '*'): Generator
	{
		foreach (parent::getIterator($fields) as $id => $entity)
		{
			$entity->output = PhpShell_Output::getStub([
				'hash' => $entity->{'output$hash'},
				'raw' =>  $entity->{'output$raw'}
			]);
			$entity->version = PhpShell_Version::getStub([
				'name' =>     $entity->{'version$name'},
				'order' =>    $entity->{'version$order'},
				'released' => $entity->{'version$released'}
			]);

			unset($entity->{'output$raw'}, $entity->{'output$hash'}, $entity->{'version$name'}, $entity->{'version$order'}, $entity->{'version$released'});

			yield $id => $entity;
		}
	}
}