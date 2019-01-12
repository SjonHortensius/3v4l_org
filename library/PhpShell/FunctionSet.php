<?php
// copied from InputSet - time for traits ?
class PhpShell_FunctionSet extends Basic_EntitySet
{
	protected $_fields = [];
	public $includesVariance = false;
	public $includesFunctionCalls = false;

	public function includeVariance()
	{
		$this->addJoin(PhpShell_Result::class, "result.input = input.id AND result.version >= 32");
		$this->_fields []= "(COUNT(DISTINCT output)-1) * 100 / COUNT(output) variance";

		$this->includesVariance = true;
		return $this;
	}

	public function includeFunctionCalls()
	{
		$this->_fields []= "(SELECT string_agg(text, ', ') FROM function WHERE id IN (SELECT function FROM \"functionCall\" WHERE input = input.id LIMIT 10)) \"functionCalls\"";

		$this->includesFunctionCalls = true;
		return $this;
	}

	protected function _query(string $fields, string $groupBy = null): Basic_DatabaseQuery
	{
		if ($fields != '*' || empty($this->_fields))
			return parent::_query($fields, $groupBy);

		$fields = 'input.*, '. implode(', ', $this->_fields);

		return parent::_query($fields, "input.id");
	}

	public function getIterator(string $fields = "*"): Generator
	{
		foreach (parent::getIterator($fields) as $id => $entity)
		{
			if (isset($this->_joins['"input"']) && $this->_joins['"input"']['return'])
			{
				// yes this somewhat sucks but getColumnMeta is way too expensive
				$entity->input = PhpShell_Input::getStub([
					'short' => $entity->short,
					'source' => $entity->source,
					'id' => $entity->id,
					'hash' => $entity->hash,
					'state' => $entity->state,
					'operationCount' => $entity->operationCount,
					'alias' => $entity->alias,
					'user' => $entity->user,
					'penalty' => $entity->penalty,
					'title' => $entity->title,
					'created' => $entity->created,
					'runArchived' => $entity->runArchived,
					'runQuick' => $entity->runQuick,
					'bughuntIgnore' => $entity->bughuntIgnore,
					'lastResultChange' => $entity->lastResultChange,
				]);

				unset($entity->short, $entity->source, $entity->hash, $entity->state, $entity->operationCount, $entity->alias, $entity->user,
					$entity->penalty, $entity->title, $entity->created, $entity->runArchived, $entity->runQuick, $entity->bughuntIgnore, $entity->lastResultChange);
			}

			yield $id => $entity;
		}
	}
}
