<?php

class PhpShell_User extends PhpShell_Entity
{
	public function login()
	{
		Basic::$database->q("UPDATE ". Basic_Database::escapeTable(self::getTable()) ." SET login_count = login_count + 1, last_login = now() WHERE id = ?", [$this->id]);
		$_SESSION['userId'] = $this->id;
		session_regenerate_id();
	}
}