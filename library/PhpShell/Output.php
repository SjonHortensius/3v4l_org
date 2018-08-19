<?php

class PhpShell_Output extends PhpShell_Entity
{
	protected $_raw;

	public function getRaw(PhpShell_Input $input, $version): string
	{
		if ($version instanceof PhpShell_Version)
			$version = $version->name;

		if (!isset($this->_raw))
			$this->_raw = ltrim(stream_get_contents($this->raw), "\n");

		$raw = substr($this->_raw, 0, 32768);
		$raw = preg_replace('~(?<![\\\])\006~', $version, $raw);
		$raw = preg_replace('~(?<![\\\])\007~', $input->short, $raw);
		return str_replace(['\\'.chr(6), '\\'.chr(7)], [chr(6), chr(7)], $raw);
	}
}