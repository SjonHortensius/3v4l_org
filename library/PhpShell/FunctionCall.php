<?php

class PhpShell_FunctionCall extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];

	// avoid lastInsertId call by overloading save
	public function save(array $data = []): bool
	{
		if (!$this->_isNew())
			throw new PhpShell_FunctionCall_CannotUpdateException('Cannot update functionCall');

		foreach ($data as $property => $value)
			$this->$property = $value;

		$this->_checkPermissions('save');

		$data = [
			'input' => $this->input->id,
			'function' => $this->function,
		];

		$columns = implode(', ', array_map([Basic_Database::class, 'escapeColumn'], array_keys($data)));
		$values = implode(', :', array_keys($data));

		$query = Basic::$database->query("INSERT INTO ". Basic_Database::escapeTable(static::getTable()) ." (". $columns .") VALUES (:". $values .")", $data);

		if (1 != $query->rowCount())
			throw new Basic_Entity_StorageException('New `%s` could not be created', [get_class($this)]);

		return true;
	}

	public function delete(): void
	{
		$this->_checkPermissions('delete');
		$this->removeCached();

		$result = Basic::$database->query(
			"DELETE FROM \"functionCall\" WHERE input = ? AND function = ?",
			[$this->input, $this->function]
		);

		if ($result != 1)
			throw new Basic_Entity_DeleteException('An error occured while deleting `%s`:`%s`', [get_class($this), $this->id]);
	}

	public static function getTable(): string
	{
		return 'functionCall';
	}
}