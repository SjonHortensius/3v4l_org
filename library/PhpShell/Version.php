<?php

class PhpShell_Version extends PhpShell_Entity
{
	protected static $_nameCache;
	protected static $_order = ['released' => false];

	public static function byName($name)
	{
		if (isset(self::$_nameCache))
		{
			if (!isset(self::$_nameCache[$name]))
				throw new Basic_Entity_NotFoundException('Did not find `%s` with name `%s`', [__CLASS__, $name]);

			return self::$_nameCache[$name];
		}

		self::$_nameCache = Basic::$cache->get('Version::list', function(){
			$res = [];

			foreach (self::find() as $version)
				$res[ $version->name ] = $version;

			return $res;
		}, 300);

		return self::$_nameCache[$name];
	}
}