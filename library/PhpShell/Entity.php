<?php

class PhpShell_Entity extends Basic_Entity
{
	protected static $_primary = 'name';
	protected static $_order = [];

	public static function getTable()
	{
		return '"'. strtolower(array_pop(explode('_', get_called_class()))) .'"';
	}
}