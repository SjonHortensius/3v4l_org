<?php

class PhpShell_Output extends PhpShell_Entity
{
	public function getRaw(PhpShell_Input $input, $version): string
	{
		if ($version instanceof PhpShell_Version)
			$version = $version->name;

		$raw = stream_get_contents($this->raw, -1, 0);

		if (false === $raw)
			throw new PhpShell_Output_CannotRetrieveRawException('Cannot fetch raw output, stream has already been closed');

		$raw = substr(ltrim($raw, "\n"), 0, 32768);
		$raw = preg_replace('~(?<![\\\])\006~', $version, $raw);
		$raw = preg_replace('~(?<![\\\])\007~', $input->short, $raw);
		return str_replace(['\\'.chr(6), '\\'.chr(7)], [chr(6), chr(7)], $raw);
	}
}