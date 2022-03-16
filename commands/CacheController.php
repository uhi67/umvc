<?php

namespace uhi67\umvc\commands;

use Exception;
use uhi67\umvc\App;
use uhi67\umvc\Command;

/**
 * @property-read App $parent
 */
class CacheController extends Command {
    
    public function beforeAction() {
        if(!$this->parent instanceof App) throw new Exception('CacheController must be a component of the App');
        if(!$this->parent->hasComponent('cache')) {
            echo 'No cache is defined. Cache can be defined in the `config/config.php` file, at `components/cache` key if needed. Example:', PHP_EOL;
            echo "\t'cache' => [\n\t\t'class' => \uhi67\umvc\FileCache::class,\n\t]", PHP_EOL;
            echo "Note: the cache class must implement the \uhi67\umvc\CacheInterface", PHP_EOL;
            return 1;
        }
        return 0;
    }
    
    public function actionDefault() {
        echo "The `cache` command operates the cache configured to the application.", PHP_EOL;
        echo "Run `php command/cache.php help` for more details.", PHP_EOL, PHP_EOL;
        return 0;
    }
    
    public function actionHelp() {
        echo "Usage:", PHP_EOL, PHP_EOL;
        echo "   php command/cache.php cleanup -- Delete the expired data from the cache.", PHP_EOL;
        echo "   php command/cache.php clear -- Delete all data from the cache.", PHP_EOL;
        return 0;
    }
    
    public function actionClear() {
        $c = $this->parent->cache->clear();
        echo "$c items deleted", PHP_EOL;
        return 0;
    }
    
    public function actionCleanup() {
        $c = $this->parent->cache->cleanup();
        echo "$c items deleted", PHP_EOL;
        return 0;
    }
}
