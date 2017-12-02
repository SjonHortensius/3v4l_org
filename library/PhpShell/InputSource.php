<?php

class PhpShell_InputSource extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
	];

	public static function create(array $data = [], bool $reload = false): Basic_Entity
	{
		$q = Basic::$database->prepare("INSERT INTO input_src VALUES(:input, :raw)");

		$stream = fopen('php://memory','r+');
		fwrite($stream, $data['raw']);
		rewind($stream);
		$data['raw'] = $stream;

		$q->execute($data);

		return self::getStub($data);
	}

	public function getRaw()
	{
		return stream_get_contents($this->raw);
	}

	public static function getTable(): string
	{
		return 'input_src';
	}
}