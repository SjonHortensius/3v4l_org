<?php

class PhpShell_Version extends PhpShell_Entity
{
	protected static $_order = ['released' => false];
	protected static $_byNameCache;
	protected static $_cache = [];

	public static function byName($name, $tryRefresh = true)
	{
		if (empty(self::$_byNameCache))
		{
			self::$_byNameCache = Basic::$cache->get('Version::list', function(){
				$res = [];

				foreach (self::find() as $version)
					$res[ $version->name ] = $version;

				return $res;
			}, 150);
		}

		if (isset(self::$_byNameCache[$name]))
			return self::$_byNameCache[$name];

		if (!$tryRefresh)
			throw new Basic_Entity_NotFoundException('Did not find `%s` with name `%s`', [__CLASS__, $name]);

		Basic::$cache->delete('Version::list');

		return self::byName($name, false);
	}

	/*
	 * FIXME this can be removed once we fully migrated to result_new and version.order gets renamed to version.id which can be done when all results are migrated
	*/
	public static function get($id): self
	{
		if (!is_scalar($id))
			throw new Basic_Entity_InvalidIdException('Invalid type `%s` for `id`', [gettype($id)]);

		if (!isset(self::$_cache[ static::class ]))
			self::$_cache[ static::class ] = [];

		if (!isset(self::$_cache[ static::class ][ $id ]))
		{
			$result = Basic::$database->q("SELECT * FROM ". Basic_Database::escapeTable(static::getTable()) ." WHERE \"order\" = ?", [$id]);
			$result->setFetchMode(PDO::FETCH_CLASS, static::class);

			// Allow caching negatives too. Note fetch() calls __construct() which stores in cache
			if (!($r = $result->fetch()))
				self::$_cache[ static::class ][ $id ] = false;
			else {
				unset($r->id); // prevent using id while migrating
				self::$_cache[static::class][$id] = $r;
			}

		}

		if (isset(self::$_cache[ static::class ][ $id ]) && false == self::$_cache[ static::class ][ $id ])
			throw new Basic_Entity_NotFoundException('Did not find `%s` with id `%s`', [static::class, $id]);

		return self::$_cache[ static::class ][ $id ];
	}
}