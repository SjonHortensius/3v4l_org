<?php

class PhpShell_ColdCacheStampede extends Basic_Memcache
{
	public function get($key, $cache_cb = null, $ttl = null)
	{
		$tries = 5;

		try
		{
			// don't pass CB because we want our custom logic to handle misses
			while (--$tries > 0 && ($result = parent::get($key)) instanceof PhpShell_ColdCacheStampede_Locked)
			{
				error_log(__METHOD__ .' - waiting for unlock of '. $key);
				sleep(1);
			}

			if ($tries == 0)
			{
				error_log(__METHOD__ .' - timeout, removing lock for '. $key);
				$this->delete($key);

				$result = $this->get($key, $cache_cb, $ttl);
			}
		}
		catch (Basic_Memcache_ItemNotFoundException $e)
		{
			if (!isset($cache_cb))
				throw $e;

			try
			{
				// add() only succeeds when entry isn't present
				$this->add($key, new PhpShell_ColdCacheStampede_Locked, $ttl);
			}
			catch (Basic_Memcache_ItemAlreadyExistsException $e)
			{
				// key was already added - verify it's not locked
				// don't pass CB because another thread is apparantly refreshing
				return $this->get($key);
			}

			ignore_user_abort(true);

			// we have obtained the lock, execute CB and overwrite lock with result
			$result = call_user_func($cache_cb);
			$this->set($key, $result, $ttl);
		}

		return $result;
	}
}

class PhpShell_ColdCacheStampede_Locked {}