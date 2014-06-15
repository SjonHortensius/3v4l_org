<?php

class PhpShell_Output extends PhpShell_Entity
{
	protected static $_primary = 'hash';

	public function getRaw(PhpShell_Input $input, $version)
	{
		if ($version instanceof PhpShell_Version)
			$version = $version->name;

		$raw = ltrim(stream_get_contents($this->raw), "\n");
		$raw = preg_replace('~(?<![\\\])\006~', $version, $raw);
		$raw = preg_replace('~(?<![\\\])\007~', $input->short, $raw);
		return str_replace(array('\\'.chr(6), '\\'.chr(7)), array(chr(6), chr(7)), $raw);
	}
}