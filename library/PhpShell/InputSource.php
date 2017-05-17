<?php

class PhpShell_InputSource extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];

	public static function create(array $data = [], bool $reload = true): Basic_Entity
	{
		$q = Basic::$database->prepare("INSERT INTO input_src VALUES(:input, :raw)");

		$stream = fopen('php://memory','r+');
		fwrite($stream, $data['raw']);
		rewind($stream);
		$data['raw'] = $stream;

		$q->execute($data, ['input' => PDO::PARAM_INT, 'raw' => PDO::PARAM_LOB]);

		return self::getStub($data);
	}

	public function getRaw()
	{
		return stream_get_contents($this->raw);
	}

	public static function getTable()
	{
		return 'input_src';
	}
}