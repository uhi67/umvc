<?php

namespace uhi67\umvc;

use Exception;

/**
 * Asset class manages external (composer-loaded) asset files (~ asset package)
 * These files are located in the vendor-path, they must be copied to a web-accessible directory.
 *
 * @property-read $name
 */
class Asset extends Component {
    /** @var string $name -- package name (default is path) */
    public $name;
    /** @var string $path -- package path relative to vendor dir or app dir ('/...') or www dir ('') */
    public $path;
    /** @var string $dir -- absolute source directory of the current asset -- computed from path if not specified */
    public $dir;
    /** @var string $id -- unique package id used as a directory name in the cache */
    public $id;
    /** @var array|null $patterns -- file patterns to select files to copy from the package path */
    public $patterns;
    /** @var string[] $files -- file names or patterns to link from the package to the view. Use '*' for all files */
    public $files = [];
    /** @var string[][] -- copy these extensions into cache together with the original files */
    public $extensions = [
        'css' => ['css.map', 'min.css', 'min.css.map'],
        'js' => ['js.map', 'min.js', 'min.js.map'],
    ];
    /** @var string $cacheDir -- web-accessible directory of the asset. E.g '/www/assets/112233445566778F' */
    public $cacheDir;
    /** @var string $cacheUrl -- url-path of the asset directory. E.g '/assets/112233445566778F' */
    public $cacheUrl;

    /**
     * Package files are copied into the cache
     * @throws Exception -- if nor path nor package name is specified 
     */
    public function init() {
        if(!$this->name && !$this->path) throw new Exception('Package name or path must be specified');
        if(!$this->name) $this->name = $this->path;

        // TODO check uníque name

        if(!$this->dir) {
            if($this->path=='') $this->dir = App::$app->basePath.'/www';
            elseif($this->path[0]=='/') $this->dir = App::$app->basePath.$this->path;
            else $this->dir = App::$app->basePath.'/vendor/'.$this->path;
        }

        if(!$this->id) $this->id = substr(md5($this->dir),0,16);
        if(!$this->cacheDir) $this->cacheDir = App::$app->basePath.'/www/assets/cache/'.$this->id;
        if(!$this->cacheUrl) $this->cacheUrl = '/assets/cache/'.$this->id;

        if(!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0774, true);
        if(!$this->patterns) $this->patterns = ['*'];

        // Copy files
        foreach($this->patterns as $pattern) {
            static::matchPattern($this->dir, '', $pattern, function($file) {
                $this->copyFile($file);
            });
        }
    }

    public function getName() {
        return $this->path ?? $this->dir;
    }

    /**
     * Iterates through files of given pattern in the `$basedir/$dir` directory and calls the callback function for every file.
     *
     * Special pattern matches:
     *
     * 		~pattern~ -- denotes a single level RegEx pattern (always begins with ~)
     *		dirPattern/subPattern -- matches directories first, then recurses subpattern
     * 		*,?		  -- single level directory or file pattern may contain legacy metacharacters
     * 		...		  -- multiple level directory pattern: matches any levels and names of subdirectories
     *
     * @param string $baseDir -- absolute package root (search and return filenames in relation to this; no trailing '/')
     * @param string $dir -- iterated subdirectory, empty or 'dir/' (must have trailing / if non-empty)
     * @param string $pattern -- literal filename, directory pattern or RegEx pattern or dir/pattern
     * @param callable $callback -- function(string $file) -- operation on found file, using path relative to $basedir
     * @param null $missing -- function(string $file) -- operation on missing file or directory
     *
     * @throws Exception
     */
    public static function matchPattern($baseDir, $dir, $pattern, $callback, $missing=null) {
        $d = $baseDir . '/' . $dir;

        // RegEx pattern (surrounded by ~)
        if(substr($pattern,0,1)=='~' && substr($pattern,-1)=='~') {
            // Single level RegEx pattern
            $dh = dir($d);
            while(($file = $dh->read($dh))!==false) {
                if($file=='.'||$file=='..') continue;
                if(filetype($d.$file)!='dir') continue;
                if(preg_match($pattern, $file)) {
                    $callback($dir.$file);
                }
            }
            $dh->close();
        }

        // Directory pattern (contains /)
        else if(($p = strpos($pattern, '/'))!==false) {
            // Match $dir and $subpattern
            $dirPattern = substr($pattern, 0, $p);
            $subpattern = substr($pattern, $p+1);
            if($dirPattern=='...') {
                // any-depth directory pattern (may contain more directory patterns, heavy recursion follows)
                $dh = dir($d);
                while(($file = $dh->read())!==false) {
                    if($file=='.'||$file=='..') continue;
                    if(filetype($d.$file)!='dir') continue;
                    static::matchPattern($baseDir, $dir.$file.'/', $subpattern, $callback);
                    static::matchPattern($baseDir, $dir.$file.'/', $pattern, $callback); // restart ... matching!
                }
                $dh->close();
            }
            elseif(preg_match('~[*?]~', $dirPattern)) {
                // Single level subdirectory pattern
                $dh = dir($d);
                while(($file = $dh->read())!==false) {
                    if($file=='.'||$file=='..') continue;
                    if(filetype($d.$file)!='dir') continue;
                    if(!fnmatch($dirPattern, $file)) continue;
                    static::matchPattern($baseDir, $dir.$file.'/', $subpattern, $callback);
                }
                $dh->close();
            }
            else {
                // literal subdirectory
                if(is_dir($d.$dirPattern)) {
                    static::matchPattern($baseDir, $dir.$dirPattern.'/', $subpattern, $callback, $missing);
                }
                else {
                    if($missing) $missing($d.$dirPattern);
                }
            }
        }

        // legacy wildcards pattern (contains * or ?)
        else if(preg_match('~[*?]~', $pattern)) {
            // match directory pattern in the current directory (use fnmatch)
	        if(!is_dir($d)) throw new Exception("Directory '$d' not found"); // note: opendir error is not catchable
            if($dh = opendir($d)) {
                while (($file = readdir($dh)) !== false) {
                    if(filetype($d . $file)!= 'file') continue;
                    if(fnmatch($pattern, $file)) {
                        $callback($dir.$file);
                    }
                }
                closedir($dh);
            }
        }

        // Single literal filename
        else {
            $fileName = $dir.$pattern;
            // Use a single file (fileName includes path relative to asset root)
            if($missing && !file_exists($baseDir . '/' . $fileName)) $missing($fileName);
            else $callback($fileName);
        }
    }

    /**
     * Copies a single file into the asset cache (if not already there) and returns the relative path
     *
     * Auto-includes:
     *        ~.css -> ~.css.map, ~.min.css, ~.min.css.map
     *        ~.js ->  ~.js.map, ~.min.js, ~.min.js.map;
     *
     * @param string $fileName -- relative fileName to the asset dir
     *
     * @return string -- cache path (relative to cacheDir)
     */
    private function copyFile($fileName) {
        $filePath = $this->dir.'/'.$fileName;   // Absolute path of original file to copy
        $cacheFileName = $this->cacheDir . '/' . $fileName; // Absolute path of cache file to copy into

        if(!file_exists($cacheFileName) || (filemtime($cacheFileName) < filemtime($filePath))) {
            if(!file_exists(dirname($cacheFileName))) mkdir(dirname($cacheFileName), 0774, true);
            copy($filePath, $cacheFileName);

            // Auto-include related extensions (.min.map.*)
            $ext = static::ext($fileName);
            if(array_key_exists($ext, $this->extensions)) {
                $baseName = substr($fileName, 0, strrpos($fileName, "."));
                foreach($this->extensions[$ext] as $ext2) {
                    $extName = $baseName . '.' . $ext2;
                    if(file_exists($this->dir . '/' . $extName)) $this->copyFile($extName);
                }
            }
        }
        return $fileName;
    }

    /**
     * Return file extension.
     *
     * If the filename contains '?', the part beginning with '?' is omitted.
     *
     * @param string $fileName
     * @return string
     */
    public static function ext($fileName) {
        if($p=strpos($fileName, '?')) $fileName = substr($fileName,0,$p);
        return strtolower(substr(strrchr($fileName, "."), 1));
    }

    /**
     * Return the url path for a specific file from an asset, or false if not exists
     *
     * @param string $fileName
     *
     * @return string|false
     */
    public function url($fileName) {
        $url = '/assets/' . $this->path;
        if(!$fileName) return $url;
        if(!file_exists($this->dir.'/'.$fileName)) return false;
        return $this->cacheUrl . '/' . $this->copyFile($fileName);
    }
}
