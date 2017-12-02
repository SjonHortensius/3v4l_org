<?php

class PhpShell_Operation extends PhpShell_Entity
{
	protected static $_primary = null;
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];
	protected static $_numerical = ['count'];

	public static function create(array $data = [], bool $reload = false): Basic_Entity
	{
		if (isset($data['operand']) && strlen($data['operand']) > 64)
			throw new PhpShell_Operation_InvalidDataException('Operand too long');

		// Cannot use parent::create because psql will log "currval of sequence "operations_id_seq" is not yet defined in this session"
		// ON CONFLICT seems redundant looking at Input::updateOperations but it really is required!
		Basic::$database->query("INSERT INTO operations VALUES (:input, :operation, :operand, :count)
			ON CONFLICT (input, operation, operand) DO UPDATE SET count = operations.count + 1", $data);

		return self::getStub($data);
	}

	public function save(array $data = []): bool
	{
		if ($this->_isNew())
			return parent::save($data);

		if (array_keys($data) != ['count'])
			throw new PhpShell_Operation_CannotUpdateException('Cannot update operations');

		$this->_checkPermissions('save');

		$this->count = $data['count'];

		if (isset($this->operand))
			Basic::$database->query(
				"UPDATE \"operations\" SET \"count\" = ? WHERE operation = ? AND operand = ? AND input = ?",
				[$this->count, $this->operation, $this->operand, $this->input]
			);
		else
			Basic::$database->query(
				"UPDATE \"operations\" SET \"count\" = ? WHERE operation = ? AND operand IS NULL AND input = ?",
				[$this->count, $this->operation, $this->input]
			);

		return true;
	}

	public function delete(): void
	{
		$this->_checkPermissions('delete');
		$this->removeCached();

		if (isset($this->operand))
			$result = Basic::$database->query(
				"DELETE FROM \"operations\" WHERE operation = ? AND operand = ? AND input = ?",
				[$this->operation, $this->operand, $this->input]
			);
		else
			$result = Basic::$database->query(
				"DELETE FROM \"operations\" WHERE operation = ? AND operand IS NULL AND input = ?",
				[$this->operation, $this->input]
			);

		if ($result != 1)
			throw new Basic_Entity_DeleteException('An error occured while deleting `%s`:`%s`', [get_class($this), $this->id]);
	}

	public static function getTable(): string
	{
		return 'operations';
	}
}