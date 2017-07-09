<?php

class PhpShell_Entity extends Basic_Entity
{
	// not all entities have an id to order on
	protected static $_order = [];

	public static function getTable(): string
	{
		return strtolower(parent::getTable());
	}
}