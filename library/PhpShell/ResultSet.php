<?php
/* Optimized EntitySet which fetches partial related entities in a single query */
class PhpShell_ResultSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesOutput = false;
	public $includesVersion = false;

	public function includeOutput()
	{
		$this->addJoin(PhpShell_Output::class, "output.id = output");
		$this->_fields[] = 'output.hash as "output$hash", output.raw as "output$raw"';

		$this->includesOutput = true;
		return $this;
	}

	public function includeVersion()
	{
		$this->addJoin(PhpShell_Version::class, "version.id = version");
		$this->_fields[] = 'version.name as "version$name", version.order as "version$order", version.released as "version$released"';

		$this->includesVersion = true;
		return $this;
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = 'input, "exitCode", '. implode(', ', $this->_fields);

		return parent::_query($fields, $groupBy);
	}

	public function getIterator(string $fields = '*'): Generator
	{
		foreach (parent::getIterator($fields) as $id => $entity)
		{
			if ($this->includesOutput)
			{
				$entity->output = PhpShell_Output::getStub([
					'hash' => $entity->{'output$hash'},
					'raw' => $entity->{'output$raw'}
				]);

				unset($entity->{'output$hash'}, $entity->{'output$raw'});
			}

			if ($this->includesVersion)
			{
				$entity->version = PhpShell_Version::getStub([
					'name' => $entity->{'version$name'},
					'order' => $entity->{'version$order'},
					'released' => $entity->{'version$released'}
				]);

				unset($entity->{'version$name'}, $entity->{'version$order'}, $entity->{'version$released'});
			}

			yield $id => $entity;
		}
	}
}