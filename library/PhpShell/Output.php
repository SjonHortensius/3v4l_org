<?php

class PhpShell_Output extends PhpShell_Entity
{
	public function getRaw(PhpShell_Input $input, $version): string
	{
		if ($version instanceof PhpShell_Version)
			$version = $version->name;

		$raw = ltrim(stream_get_contents($this->raw), "\n");

		if ($raw == "")
			throw new PhpShell_Output_CannotRetrieveRawException('Cannot fetch raw output, stream has already been closed');

		$raw = substr($raw, 0, 32768);
		$raw = preg_replace('~(?<![\\\])\006~', $version, $raw);
		$raw = preg_replace('~(?<![\\\])\007~', $input->short, $raw);
		return str_replace(['\\'.chr(6), '\\'.chr(7)], [chr(6), chr(7)], $raw);
	}
}