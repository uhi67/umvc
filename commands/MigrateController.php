<?php
namespace uhi67\umvc\commands;

use Exception;
use Throwable;
use uhi67\umvc\ArrayHelper;
use uhi67\umvc\CliHelper;
use uhi67\umvc\Command;
use uhi67\umvc\Connection;
use uhi67\umvc\Migration;
use uhi67\umvc\SqlMigration;

class MigrateController extends Command {

    /**
     * @var Connection|null 
     */
    private $connection;
    private $confirm, $verbose, $migrationTable, $migrationPath;

    /**
     * @throws Exception
     */
    public function beforeAction() {
        $this->connection = $this->app->db;
        if(!$this->connection) throw new Exception('No database connection defined');
        $this->verbose = ArrayHelper::fetchValue($this->query, 'verbose', $verbose ?? 1);
        $this->confirm = ArrayHelper::fetchValue($this->query, 'confirm');
        // Environment-specific values may be set in the config
        $this->migrationTable = $this->connection->migrationTable ?? 'migration';
        $this->migrationPath = $this->connection->migrationPath ?? dirname(__DIR__,4) . '/migrations';
        return true;
    }

    public function actionDefault() {
        
        echo "The migrate command keeps database changes in sync with source code.", PHP_EOL;
        echo "Run `php command/migrate.php help` for more details.", PHP_EOL, PHP_EOL;
        return $this->actionUp();
    }

    /**
     * Migrate up the database to the current state
     * 
     * @return int
     * @throws Exception
     */
    public function actionUp() {
        if (!$this->createMigrationTable()) exit(1);
        if (!$this->createMigrationPath()) exit(2);
        // Collect new migration files
        $dh = opendir($this->migrationPath);
        if(!$dh) throw new Exception("Invalid dir ".$this->migrationPath);
        $new = [];
        while (($file = readdir($dh)) !== false) {
            if(filetype($this->migrationPath.'/'.$file)=='file' && preg_match('/^m\d{6}_\d{6}_[\w_]+\.(php|sql)$/', $file)) {
                // Check in database
                $name = pathinfo($file, PATHINFO_FILENAME);
                $applied = \uhi67\umvc\models\Migration::getOne(['name'=>$name]);
                if(!$applied) $new[] = $file;
            }
        }
        closedir($dh);

        // List new migrations
        if(empty($new)) {
            if($this->verbose) echo "Everything is up to date!", PHP_EOL;
            exit;
        }
        sort($new);
        if($this->verbose) {
            echo sprintf("There are %d new updates:", count($new)), PHP_EOL;
            foreach($new as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $filename = $this->migrationPath . '/' . $file;
                echo "  - $filename", PHP_EOL;
            }
            if($this->confirm!='yes' && !CliHelper::confirm('Apply all the new migrations?')) exit;
        }

        // Execute new migrations
        $n = 0;
        foreach($new as $file) {
            $this->connection->pdo->beginTransaction();
            $name = pathinfo($file, PATHINFO_FILENAME);
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $filename = $this->migrationPath.'/'.$file;
            try {
                if($this->verbose>1) echo "Applying $name from $filename", PHP_EOL;
                if($ext=='php') {
                    $success = false;

                    // Run php file
                    try {
                        /** @var Migration $className */
                        $className = '\app\migrations\\'.$name;
                        require $filename;
                        /** @var Migration $migration */
                        $migration = new $className(['app'=>$this->app, 'connection'=>$this->connection, 'verbose'=>$this->verbose]);
                        $success = $migration->up();
                    }
                    catch(Exception $e) {
                        if($this->verbose) echo sprintf('%s in file %s at line %s', $e->getMessage(), $e->getFile(), $e->getLine()), PHP_EOL;
                        if($this->verbose>1) debug_print_backtrace();
                    }
                    if($success) {
                        // Insert into database
                        $migrationDone = new \uhi67\umvc\models\Migration([
                            'name' => $name,
                            'applied' => time(),
                        ]);
                        if(!$migrationDone->save()) echo "Registering migration " . $n . " failed.", PHP_EOL,
                        $migrationDone->lastQuery->lastError(), PHP_EOL, $migrationDone->lastQuery->sql, PHP_EOL;
                        else $n++;
                    } else {
                        echo "Applying migration '" . $name . "' failed", PHP_EOL;
                        $this->connection->pdo->rollBack();
                        break;
                    }
                }
                else {
                    // SQL-type migration
                    $migration = new SqlMigration(['connection'=>$this->connection, 'filename'=>$filename]);
                    $success = $migration->up();

                    if($success) {
                        if($this->verbose > 1) {
                            echo "Migration " . $name . " has been applied.", PHP_EOL;
                            throw new Exception("Registering migration " . $n . " failed.");
                        }

                        $this->connection->reset();

                        // Insert into database
                        $migrationDone = new \uhi67\umvc\models\Migration([
                            'name' => $name,
                            'applied' => time(),
                        ]);
                        if(!$migrationDone->save()) {
                            echo "Registering migration " . $n . " failed.", PHP_EOL;
                            throw new Exception("Registering migration " . $n . " failed.");
                        }
                        else $n++;
                    } else {
                        echo "Applying migration '" . $name . "' failed", PHP_EOL;
                        echo $this->connection->lastError;
                        $this->connection->pdo->rollBack();
                        break;
                    }
                }
                $this->connection->pdo->commit();
            }
            catch(Throwable $e) {
                $this->connection->pdo->rollBack();
                throw new Exception("Applying migration '". $name."' caused an exception", 500, $e);
            }
        }
        if($n==0 && $this->verbose) echo "No migrations applied", PHP_EOL;
        else {
            // If migrations were applied, the model table metadata cache must be cleared
            if($this->app->cache) $this->app->cache->clear();

            // Summary
            if($this->verbose) echo PHP_EOL, $n, $n>1 ? " migrations were" : " migration was", " applied.", PHP_EOL;
        }
        return 0;
    }
    
    public function actionHelp() {
        echo "Place plain SQL or PHP migration files into `/migrations/` directory.", PHP_EOL;
        echo "The default action creates the `migration` table which track the changes in your database, and applies all new migrations.", PHP_EOL, PHP_EOL;
        echo "Usage:", PHP_EOL, PHP_EOL;
        echo "   php app.php migrate verbose=2 -- migrate up with detailed output; verbose=0 for silent operation.", PHP_EOL;
        echo "   php app.php migrate/create <name> -- create new php migration in the migration directory", PHP_EOL;
    }

    /**
     * @throws Exception
     */
    public function actionCreate() {
        if(!$this->connection) throw new Exception('No database connection defined');
        if (!$this->createMigrationTable()) exit(1);
        if (!$this->createMigrationPath()) exit(2);
        $name = array_shift($this->query);
        return $this->createMigration($name);
    }

    /**
     * Creates the migration table if not exists
     *
     * @return bool -- success
     * @throws Exception
     */
    public function createMigrationTable() {
        if(!$this->connection->tableExists($this->migrationTable)) {
            if($this->confirm!='yes' && !CliHelper::confirm('The `migration` table does not exist. Create?')) exit;
            echo "Creating `migration` table...", PHP_EOL;
            $this->connection->pdo->query('CREATE TABLE '.$this->migrationTable.' (name varchar(100) unique primary key, applied int)');
            if(!$this->connection->tableExists($this->migrationTable)) {
                echo "Creating `migration` table failed", PHP_EOL;
                return false;
            }
        }
        return true;
    }

    /**
     * Checks and creates migration directory if not exists
     *
     * @return bool -- success
     */
    public function createMigrationPath() {
        if(!is_dir($this->migrationPath)) {
            if($this->confirm!='yes' && !CliHelper::confirm('The `/migrations` directory does not exist. Create?')) exit;
            echo "Creating `migrations` directory...", PHP_EOL;
            if(!mkdir($this->migrationPath, true)) {
                echo "Creating `migrations` directory failed", PHP_EOL;
                return false;
            }
        }
        return true;
    }

    /**
     * Creates a new migration
     *
     * @param string $name
     *
     * @return int -- exit status, 0=OK
     */
    public function createMigration($name) {
        if(!$name) {
            echo 'The migration name must be specified.', PHP_EOL;
            return 3;
        }
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            echo 'The migration name should contain letters, digits, underscore and/or backslash characters only.', PHP_EOL;
            return 4;
        }
        $shortClassName = 'm' . gmdate('ymd_His') . '_' . $name;
        if(strlen($shortClassName) > 100) {
            echo 'The migration name is too long.', PHP_EOL;
            return 4;
        }
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $shortClassName . '.php';
        if(file_exists($file)) {
            echo 'A migration file with name '.$name.' already exists.', PHP_EOL;
            return 5;
        }
        if($this->confirm!='yes' && !CliHelper::confirm("Create new migration '$file'?")) exit(-1);
        $content = <<<EOT
<?php
namespace app\migrations;
use uhi67\umvc\Migration;

class %className% extends Migration {
	public function up() {
	}
}
EOT;
        $content = str_replace('%className%', $shortClassName, $content);
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            echo "Failed to create new migration.", PHP_EOL;
            return 6;
        }
        echo "New migration created successfully.", PHP_EOL;
        return 0;
    }
}
