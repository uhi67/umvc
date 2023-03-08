<?php

namespace uhi67\umvc;

use Exception;

/**
 * # File cache
 *
 * ### config: cache
 *        path    -- absolute path to save files. Default is datapath/cache
 *        ttl        -- default ttl (def 900 = 15 min)
 *
 * Data will be saved to the file with give name,
 * the ttl value into file `_ttl_name` which content is "created,expires" in unix timestamp format.
 *
 * ### Usage
 * see {@see CacheInterface}
 *
 * @package UMVC Simple Application Framework
 */
class FileCache extends Component implements CacheInterface {
    /** @var string $path -- directory to store cached data (path name without trailing '/') */
    public $path;
    /** @var int $ttl -- default time-to-live value of the cache entries in sec. Default is 900, 15 minutes */
    public $ttl;

    /**
     * @throws Exception
     */
    public function init() {
        if(!$this->path) $this->path = App::$app->runtimePath . '/cache';
        if(!file_exists($this->path)) if(!mkdir($this->path)) throw new Exception("Cannot create cache directory " . $this->path);
        if(!$this->ttl) $this->ttl = 900; // 15 minutes
    }

    /**
     * Returns data from cache or null if not found or expired.
     *
     * @param string $key
     * @param null $default
     * @param null $ttl -- if given, overrides expiration (only for this query)
     *
     * @return mixed
     */
    public function get($key, $default = null, $ttl = null) {
        $filename = static::fileName($key);
        if(!file_exists($filename)) return $default;
        $ttlname = static::ttlName($key);
        if(!file_exists($ttlname)) {
            unlink($filename);
            return $default;
        }
        [$created, $expires] = explode(',', file_get_contents($ttlname));
        if($ttl) $expires = $created + $ttl;
        if(time() > $expires) {
            unlink($filename);
            unlink($ttlname);
            return $default;
        }
        return unserialize(file_get_contents($filename));
    }

    public function has($key) {
        $filename = static::fileName($key);
        if(!file_exists($filename)) return false;
        $ttlname = static::ttlName($key);
        if(!file_exists($ttlname)) {
            unlink($filename);
            return false;
        }
        $item = explode(',', file_get_contents($ttlname));
        $expires = $item[1];
        if(time() > $expires) {
            unlink($filename);
            unlink($ttlname);
            return false;
        }
        return true;
    }

    /**
     * Removes a cache item by key or key pattern.
     * If the exact key is found in the cache, only that item will be removed.
     * Any other case all items containing the pattern is removed.
     * Pattern should be a partial key or a valid regEx. Invalid regEx fails silently.
     *
     * @param string $key -- key or pattern
     *
     * @return int -- number of deleted items, false on error
     */
    public function delete($key) {
        $filename = static::fileName($key);
        if(file_exists($filename)) {
            $ttlname = static::ttlName($key);
            unlink($filename);
            if(file_exists($ttlname)) unlink($ttlname);
            return 1;
        }
        return self::deletePattern($key, '');
    }

    /**
     * Remove keys by pattern recursively
     *
     * @param $key -- partial key or regEx
     * @param $subdir
     * @return int
     */
    private function deletePattern($key, $subdir) {
        $c = 0;
        $dir = $this->path . $subdir;
        $dh = opendir($dir);
        while(($file = readdir($dh)) !== false) {
            // Skip non-files and  _ttl_ files now
            if(in_array($file, ['.', '..'])) continue;
            if(substr($file, 0, 5) == '_ttl_') continue;
            if(filetype($dir . '/' . $file) == 'dir') {
                $c += self::deletePattern($key, '/' . $file);
                if(self::isEmpty($dir . '/' . $file)) rmdir($dir . '/' . $file);
                continue;
            }
            if(filetype($dir . '/' . $file) != 'file') continue;

            // Key filter
            if(strpos($file, $key) || @preg_match($key, $file)) {
                $filename = $dir . '/' . $file;
                $ttlname = $dir . '/_ttl_' . $file;
                if(file_exists($ttlname)) unlink($ttlname);
                unlink($filename);
                $c++;
            }
        }
        closedir($dh);
        return $c;
    }

    /**
     * Saves data into cache. Null value will never be cached.
     *
     * @param string $key
     * @param mixed $value -- value to store, null to remove the item
     * @param int|null $ttl -- time to live in secs, default is given at cache config
     * @return mixed -- the value itself
     */
    public function set($key, $value, $ttl = null) {
        $filename = $this->fileName($key);
        $ttlname = $this->ttlName($key);
        if(!file_exists(dirname($filename))) mkdir(dirname($filename), 0774);

        if(file_exists($filename)) unlink($filename);
        if(file_exists($ttlname)) unlink($ttlname);
        if($value === null) return null;

        if(!$ttl) $ttl = $this->ttl;
        file_put_contents($ttlname, time() . ',' . (time() + $ttl));
        file_put_contents($filename, serialize($value));
        return $value;
    }

    /**
     * Returns the cache filename for the key
     *
     * @param $name
     * @return string
     */
    protected function fileName($name) {
        $hash = crc32($name);
        $name = substr($hash, 2) . '_' . AppHelper::toNameID($name);
        $dir = substr($hash, 0, 2);
        return $this->path . '/' . $dir . '/' . $name;
    }

    /**
     * Returns the cache filename of the ttl-data for the key
     *
     * @param $name
     * @return string
     */
    protected function ttlName($name) {
        $hash = crc32($name);
        $name = substr($hash, 2) . '_' . AppHelper::toNameID($name);
        $dir = substr($hash, 0, 2);
        return $this->path . '/' . $dir . '/_ttl_' . $name;
    }

    /**
     * Clears all expired items from the cache
     * (default ttl may be overridden, only older items will be deleted, no other items affected)
     *
     * @param int|null $ttl
     *
     * @return int -- number of items deleted
     */
    public function cleanup($ttl = null) {
        return $this->cleanupInner($ttl);
    }

    /**
     * Clears expired items from the file cache directory, recursively
     *
     * @param int|null $ttl
     * @param $subpath
     * @return int
     */
    private function cleanupInner($ttl, $subpath = '') {
        $c = 0;
        $dir = $this->path . $subpath;
        $dh = opendir($dir);
        while(($file = readdir($dh)) !== false) {
            // Skip non-files and  _ttl_ files now
            if(in_array($file, ['.', '..'])) continue;
            if(substr($file, 0, 5) == '_ttl_') continue;
            if(filetype($dir . '/' . $file) == 'dir') {
                $c += $this->cleanupInner($ttl, '/' . $file);
                if(self::isEmpty($dir . '/' . $file)) rmdir($dir . '/' . $file);
                continue;
            }
            if(filetype($dir . '/' . $file) != 'file') continue;

            $filename = $dir . '/' . $file;
            $ttlname = $dir . '/_ttl_' . $file;
            if(!file_exists($ttlname)) {
                unlink($filename);
                $c++;
                continue;
            }

            [$created, $expires] = explode(',', file_get_contents($ttlname));

            // Debug only
            // $remained = $expires - time();
            // echo " * $subpath/$file: $created, $expires ($remained)",PHP_EOL;

            if($ttl) $expires = $created + $ttl;
            if(time() > $expires) {
                unlink($filename);
                unlink($ttlname);
                $c++;
            }
        }
        closedir($dh);
        return $c;
    }

    /**
     * deletes all data from the cache
     *
     * @param $subPath -- subdirectory name in the cache. Default the deletion is starting at the root.
     * @return int -- number of entries deleted
     */
    public function clear($subPath = '') {
        $c = 0;
        if($subPath) $subPath = str_replace('..', '.', $subPath); // Prevent back-step for security
        $path = $this->path . $subPath;
        $dh = opendir($path);
        while(($file = readdir($dh)) !== false) {
            if(in_array($file, ['.', '..'])) continue;
            if(filetype($path . '/' . $file) == 'dir') {
                $c += self::clear('/' . $file);
                rmdir($path . '/' . $file);
                continue;
            }
            // Skip non-files and  _ttl_ files now
            if(filetype($path . '/' . $file) != 'file') continue;
            $filename = $path . '/' . $file;
            unlink($filename);
            if(substr($file, 0, 5) != '_ttl_') $c++;
        }
        closedir($dh);
        return $c;
    }

    /**
     * @param string $key -- the name of the cached value
     * @param callable $compute -- the function retrieves the original value
     * @param int $ttl -- time to live in seconds (used in set only)
     * @param bool $refresh -- set to true to force replace the cached value
     *
     * @return bool|int
     */
    public function cache($key, callable $compute, $ttl = null, $refresh = false) {
        if(!$refresh && ($value = $this->get($key)) !== null) return $value;
        return $this->set($key, $compute(), $ttl);
    }

    public function finish() {
    }

    private static function isEmpty($dir) {
        $handle = opendir($dir);
        while(false !== ($entry = readdir($handle))) {
            if($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }
}
