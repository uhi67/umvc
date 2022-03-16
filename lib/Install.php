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
     * 4. Version number
     *
	 * @throws Exception
	 */
	public static function postInstall() {
		echo "Running application's postInstall\n";
		$root = dirname(__DIR__, 3);

		// 1. Create runtime dir
		if(!file_exists($root.'/runtime')) {
			mkdir($root.'/runtime', 0774);
		}

        // 2. Empty cache
        try {
            /** @var App $app */
            $app = require dirname(__DIR__, 3) . '/app.php';
            if ($app->cache) {
                $c = $app->cache->clear();
                echo "$c items deleted from the cache.", PHP_EOL;
            } else {
                echo "No cache is defined.", PHP_EOL;
            }
        }
        catch (Exception $e) {
            echo "Failed to instantiate the application, the cache was not cleared.", PHP_EOL;
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
        // 4. Version number
		$version = trim(exec('git describe --tags --abbrev=1'));
		if($version) {
		    $versionOutputFilename = dirname(__DIR__).'/version.txt';
		    file_put_contents($versionOutputFilename, $version);
		    echo "Application version is $version (logged into $versionOutputFilename)\n\n";
		}

        // File owners. Runs only if www-data user exists.
        shell_exec('bash -c "if id -u www-data &>/dev/null; then chown www-data:www-data .env ; chown -R www-data:www-data runtime ; fi"');
	}

    /**
     * Clear the directory with files
     *
     * @param string $path
     * @return void
     * @throws Exception
     */
    private static function clearDir(string $path) {
        if(!is_dir($path)) throw new Exception('Invalid directory: '.$path);
        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if(in_array($file, ['.', '..'])) continue;
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
}
