<?php

namespace uhi67\umvc;

/**
 * # Cache interface
 *
 * Cache classes must implement it.
 *
 * ### Usage
 * - set(key, value, [ttl]) -- sets value and ttl in the cache for the key
 * - get(key, [default, [ttl]]) -- returns the value if key exists (and not expired) in the cache, or default (null) if not.
 * - has(key)        -- returns true if key exists and not expired in the cache
 * - delete(key)    -- deletes single or multiple items from the cache by name or RegEx pattern
 * - clear()        -- deletes all data from the cache
 * - cleanUp()        -- deletes all expired data from the cache
 * - cache($key, callback, ttl) -- gets value if exists, or computes and sets otherwise. Restarts ttl.
 * - finish()        -- called before destructor (e.g. to save data to physical store if needed)
 *
 * @package UMVC Simple Application Framework
 */
interface CacheInterface {
    /**
     * Returns data from cache or null if not found or expired.
     *
     * @param string $key
     * @param mixed $default
     * @param int|bool|null $ttl -- if given, overrides and restarts expiration (only for this query, and if the element is not purged yet). true=restart default ttl
     *
     * @return mixed
     */
    public function get($key, $default = null, $ttl = null);

    /**
     * Returns data from cache or null if not found or expired.
     * (Side effect: deletes expired data)
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key);

    /**
     * Saves data into cache
     *
     * @param string $key
     * @param mixed $value -- Specify null to remove item from the cache
     * @param int $ttl -- time to live in secs, default is given at cache config, false to no expiration
     * @return int -- number of bytes written or false on failure
     */
    public function set($key, $value, $ttl = null);

    /**
     * Removes given items from the cache by name or pattern
     *
     * @param string $key -- key or key pattern
     *
     * @return int -- number of deleted items, false on error
     */
    public function delete($key);

    /**
     * Deletes all data from the cache
     *
     * @return int -- the number of items deleted
     */
    public function clear();

    /**
     * called before destructor (e.g. to save data to physical store if needed)
     *
     * @return void
     */
    public function finish();

    /**
     * Returns a cached value or computes it if not exists
     *
     * @param string $key -- the name of the cached value
     * @param callable $compute -- the function retrieves the original value
     * @param int $ttl -- time to live in seconds (used in set only)
     * @param bool $refresh -- set to true to force replace the cached value
     *
     * @return mixed -- the cached value
     */
    public function cache($key, callable $compute, $ttl = null, $refresh = false);

    /**
     * Must clean up the expired items
     * (default ttl may be overridden, only older items will be deleted, no other items affected)
     *
     * @param int|null $ttl
     *
     * @return int -- number of items deleted
     */
    public function cleanup($ttl = null);
}
