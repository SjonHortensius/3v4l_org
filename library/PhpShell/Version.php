<?php

class PhpShell_Version extends PhpShell_Entity
{
	protected static $_order = ['released' => false];

	public static function byName($name, $tryRefresh = true)
	{
		$cache = Basic::$cache->get('Version::list', function(){
			$res = [];

			foreach (self::find() as $version)
				$res[ $version->name ] = $version;

			return $res;
		}, 150);

		if (isset($cache[$name]))
			return $cache[$name];

		if (!$tryRefresh)
			throw new Basic_Entity_NotFoundException('Did not find `%s` with name `%s`', [__CLASS__, $name]);

		Basic::$cache->delete('Version::list');

		return self::byName($name, false);
	}
}