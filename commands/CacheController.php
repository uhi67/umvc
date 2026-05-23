<?php /** @noinspection PhpIllegalPsrClassPathInspection */

/** @noinspection PhpUnused */

namespace educalliance\umvc\commands;

use Exception;
use educalliance\umvc\App;
use educalliance\umvc\Command;

/**
 * @property-read App $parent
 */
class CacheController extends Command {

	/**
	 * @throws Exception
	 */
	public function beforeAction(): bool
    {
        if(!$this->app->hasComponent('cache')) {
            echo 'No cache is defined. Cache can be defined in the `config/config.php` file, at `components/cache` key if needed. Example:', PHP_EOL;
            echo "\t'cache' => [\n\t\t'class' => \educalliance\umvc\FileCache::class,\n\t]", PHP_EOL;
            echo "Note: the cache class must implement the \educalliance\umvc\CacheInterface", PHP_EOL;
            return false;
        }
        return true;
    }
    
    public function actionDefault(): int
    {
        echo "The `cache` command operates the cache configured to the application.", PHP_EOL;
        echo "Run `php app cache/help` for more details.", PHP_EOL, PHP_EOL;
        return 0;
    }
    
    public function actionHelp(): int
    {
        echo "Usage:", PHP_EOL, PHP_EOL;
        echo "   php app cache/cleanup -- Delete the expired data from the cache.", PHP_EOL;
        echo "   php app cache/clear -- Delete all data from the cache.", PHP_EOL;
        return 0;
    }
    
    public function actionClear(): string
    {
        $c = $this->app->cache->clear();
        return "$c items deleted" . PHP_EOL;
    }
    
    public function actionCleanup(): string
    {
        $c = $this->app->cache->cleanup();
        return "$c items deleted" . PHP_EOL;
    }
}
