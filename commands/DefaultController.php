<?php
namespace educalliance\umvc\commands;

use Exception;
use educalliance\umvc\Ansi;
use educalliance\umvc\App;
use educalliance\umvc\AppHelper;
use educalliance\umvc\ArrayHelper;
use educalliance\umvc\Command;

class DefaultController extends Command {

    /**
     * Default action: List commands
     *
     * @return int
     * @throws Exception
     */
    public function actionDefault(): int {
        $name = $this->app->title ?: 'UMVC';
        echo Ansi::color($name, 'green'), PHP_EOL;
        echo "Index of commands\n";
        $appPath = $this->app->basePath;
        $umvcPath = dirname(__DIR__);
        $commands = [];

        // UMVC commands
        $dir = $umvcPath.'/commands';
        $dh = opendir($dir);
        if(!$dh) throw new Exception("Invalid dir $dir");
        while (($file = readdir($dh)) !== false) {
            if(filetype($dir.'/'.$file)=='file' && preg_match('/^(\w+)Controller.php/', $file, $m)) {
                $command = $m[1];
                $className = App::namespace().'\commands\\'.$m[1].'Controller';
                $commands[$command] = $className;
            }
        }
        closedir($dh);

        // User commands (overwrites umvc commands)
        $dir = $appPath.'/commands';
        if(is_dir($dir)) {
            $dh = opendir($dir);
            if(!$dh) throw new Exception("Invalid dir $dir");
            while (($file = readdir($dh)) !== false) {
                if(filetype($dir.'/'.$file)=='file' && preg_match('/^(\w+)Controller.php/', $file, $m)) {
                    $command = $m[1];
                    $className = 'app\commands\\'.$m[1].'Controller';
                    $commands[$command] = $className;
                }
            }
            closedir($dh);
        }

        foreach($commands as $command=>$className) {
            echo '- ', Ansi::color(AppHelper::underscore($command, '-'), 'blue')." \n\tActions:\n";
            if(!class_exists($className)) throw new Exception("Class $className not found");
            $methods = get_class_methods($className);
            $descriptions = is_callable([$className, 'descriptions']) ? call_user_func([$className, 'descriptions']) : [];
            foreach($methods as $method) {
                if(preg_match('/^action([A-Z]\w+)/', $method, $m) && $m[1]!='Default') {
                    $action = AppHelper::underscore($m[1], '-');
                    $description = ArrayHelper::getValue($descriptions, $action);
                    echo Ansi::color("\t- ".sprintf('%-12s', $action), 'green')."\t$description\n";
                }
            }
        }
        return App::EXIT_STATUS_OK;
    }
}
