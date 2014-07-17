<?php

class PhpShell_User extends PhpShell_Entity
{
	protected static $_primary = 'id';

	public function login()
	{
		Basic::$database->query("UPDATE ". self::getTable() ." SET login_count = login_count + 1 WHERE name = ?", [$this->name]);
		$_SESSION['userId'] = $this->id;
		session_regenerate_id();
	}
}