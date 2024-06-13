<?php
namespace uhi67\umvc;


use Exception;

class Install {
    /**
     * Run by composer after install.
     * Warning: composer may be run by root, be careful with owners of created files
     *
     * 1. create runtime dir
     * 2. Empty cache
     * 3. Empty assets cache
     *
     * @throws Exception
     */
    public static function postInstall() {
        $configFile = dirname(__DIR__, 4) . '/config/config.php';
        $config = include $configFile;
        defined('ENV') || define('ENV', $config['application_env'] ?? 'production');
        defined('ENV_DEV') || define('ENV_DEV', ENV != 'production');

        echo "Running application's postInstall\n";
        $root = dirname(__DIR__, 3);

        // 1. Create runtime dir
        if(!file_exists($root.'/runtime')) {
            mkdir($root.'/runtime', 0774);
        }

        // 2. Empty cache
        try {
            /** @var App $app */
            $app = App::create(['config'=>$config]);
            if ($app->hasComponent('cache')) {
                $c = $app->cache->clear();
                echo "$c items deleted from the cache.", PHP_EOL;
            } else {
                echo "No cache is defined.", PHP_EOL;
            }
        }
        catch (Exception $e) {
            echo "Failed to instantiate the application, the cache was not cleared.", PHP_EOL;
            if(ENV_DEV) AppHelper::showException($e);
        }

        // 3. Empty asset cache
        try {
            $cacheDir = App::$app->basePath.'/www/assets/cache/';
            static::clearDir($cacheDir);
            echo "Asset cache cleared.", PHP_EOL;
        }
        catch (Exception $e) {
            echo "Failed to clear the asset cache. ".$e->getMessage(), PHP_EOL;
        }
    }

    /**
     * Clear the directory with files
     *
     * @param string $path
     * @return void
     * @throws Exception
     */
    public static function clearDir(string $path) {
        if(!is_dir($path)) throw new Exception('Invalid directory: '.$path);
        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if(in_array($file, ['.', '..', '.gitignore', '.gitkeep'])) continue;
            if(filetype($path . '/' . $file)== 'dir') {
                self::clearDir($path.'/'.$file);
                rmdir($path.'/'.$file);
                continue;
            }
            if(filetype($path . '/' . $file)!= 'file') continue;
            $filename = $path . '/' . $file;
            unlink($filename);
        }
        closedir($dh);
    }

    /**
     * copies multiple files from source to destination directory
     *
     * @param string $src -- source directory or file
     * @param string $dst -- destination directory or file
     * @param bool $overwrite
     * @return int -- number of files copied
     */
    public static function rcopy($src, $dst, $overwrite=false) {
        if (is_dir($src)) {
            if (!file_exists($dst)) mkdir($dst);
            $files = scandir($src);
            $c = 0;
            foreach ($files as $file) {
                if ($file != "." && $file != "..")
                    $c += self::rcopy("$src/$file", "$dst/$file", $overwrite);
            }
            return $c;
        }
        else if(file_exists($src) && !file_exists($dst) || $overwrite) {
            return copy($src, $dst) ? 1 : 0;
        }
        return 0;
    }
}
