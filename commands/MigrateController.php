<?php /** @noinspection PhpUnused */

namespace uhi67\umvc\commands;

use Codeception\Util\Debug;
use Exception;
use Throwable;
use uhi67\umvc\AppHelper;
use uhi67\umvc\ArrayHelper;
use uhi67\umvc\CliHelper;
use uhi67\umvc\Command;
use uhi67\umvc\Connection;
use uhi67\umvc\Migration;
use uhi67\umvc\SqlMigration;

/**
 * Migration command
 *
 * Usage
 * =====
 *
 * - run `php app migrate` to migrate up
 * - New migration can be created by `php app migrate/create <name>`
 * - `php app migrate/reset` -- delete database and migrate up from the beginning
 * - use `confirm=yes` switch to avoid interactive confirmations
 *
 */
class MigrateController extends Command {

    /**
     * @var Connection|null
     */
    private $connection;
    private $confirm, $verbose, $migrationTable, $migrationPath;

    /**
     * Prepares the actual action.
     *
     * - Initializes the database connection and global migration parameters
     * - Reads the common command-line switches (verbose, confirm),
     *
     * @throws Exception
     */
    public function beforeAction() {
        $this->connection = $this->app->db;
        if(!$this->connection) throw new Exception('No database connection defined');
        $this->verbose = ArrayHelper::fetchValue($this->query, 'verbose', $verbose ?? 1);
        $this->confirm = ArrayHelper::fetchValue($this->query, 'confirm');
        // Environment-specific values can be set in the config
        $this->migrationTable = $this->connection->migrationTable ?? 'migration';
        $this->migrationPath = $this->connection->migrationPath ?? $this->app->basePath . '/migrations';
        return true;
    }

    public function actionDefault() {
        if($this->verbose) {
	        echo "The migrate command keeps database changes in sync with source code.", PHP_EOL;
	        echo "Run `php app migrate/help` for more details.", PHP_EOL, PHP_EOL;
        }
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

        $migrationPath = ArrayHelper::fetchValue($this->query, 'path', $this->migrationPath);

        // Collect new migration files
	    if($this->verbose>2) echo "Migrating from path '$migrationPath'", PHP_EOL;
        $dh = opendir($migrationPath);
        if(!$dh) throw new Exception("Invalid dir " . $migrationPath);
        $new = [];
        while (($file = readdir($dh)) !== false) {
            if(filetype($migrationPath.'/'.$file)=='file' && preg_match('/^m\d{6}_\d{6}_[\w_]+\.(php|sql)$/', $file)) {
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
            return 0;
        }
        sort($new);
        if($this->verbose) {
            echo sprintf("There are %d new updates:", count($new)), PHP_EOL;
            foreach($new as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $filename = $migrationPath . '/' . $file;
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
            $filename = $migrationPath.'/'.$file;
            try {
                if($this->verbose>1) echo "Applying $name from $filename", PHP_EOL;
                if($ext=='php') {
                    $success = false;

                    // Run php file
                    try {
                        /** @var Migration $className */
                        $className = '\app\migrations\\'.$name;
                        require $filename;
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
                        if($this->connection->pdo->inTransaction()) $this->connection->pdo->rollBack();
                        break;
                    }
                }
                else {
                    // SQL-type migration
                    $migration = new SqlMigration(['connection'=>$this->connection, 'filename'=>$filename, 'verbose'=>$this->verbose]);
                    $success = $migration->up();

                    if($success) {
                        if($this->verbose > 1) {
                            echo "Migration " . $name . " has been applied.", PHP_EOL;
                        }

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
                        if($this->connection->pdo->inTransaction()) $this->connection->pdo->rollBack();
                        break;
                    }
                }
				// Note: MySQL auto-commits transactions on DDL statements. Therefore we may find our transaction already gone
	            if($this->connection->pdo->inTransaction()) {
					$this->connection->pdo->commit();
	            }
            }
            catch(Throwable $e) {
                if($this->connection->pdo->inTransaction()) $this->connection->pdo->rollBack();
                printf("Exception: %s in file %s at line %d\n", $e->getMessage(), $e->getFile(), $e->getLine());
                throw new Exception("Applying migration '". $name."' caused an exception", 500, $e);
            }
        }
        if($n==0 && $this->verbose) echo "No migrations applied", PHP_EOL;
        else {
            // If migrations were applied, the model table metadata cache must be cleared
            if($this->app->hasComponent('cache')) $this->app->cache->clear();

            // Summary
            if($this->verbose) echo PHP_EOL, $n, $n>1 ? " migrations were" : " migration was", " applied.", PHP_EOL;
        }
        return 0;
    }

    public function actionHelp() {
        echo "Place plain SQL or PHP migration files into `/migrations/` directory.", PHP_EOL;
        echo "The default action creates the `migration` table which track the changes in your database, and applies all new migrations.", PHP_EOL, PHP_EOL;
        echo "Usage:", PHP_EOL, PHP_EOL;
        echo "   `php app migrate` -- migrate up. Interactive confirmations will be asked for.", PHP_EOL;
        echo "   `php app migrate/up verbose=2` -- migrate up with detailed output; `verbose=0` for silent operation.", PHP_EOL;
        echo "   `php app migrate/up path=<path>` -- migrate using a custom directory.", PHP_EOL;
        echo "   `php app migrate/create <name>` -- create new php migration in the migration directory", PHP_EOL;
	    echo "   `php app migrate/reset` -- delete database and migrate up from the beginning", PHP_EOL, PHP_EOL;
		echo "Options:", PHP_EOL, PHP_EOL;
	    echo "   - `confirm=yes` to avoid interactive confirmations", PHP_EOL;
	    echo "   - `verbose=0` for silent operation, 1 for normal, 2 for detailed output", PHP_EOL;
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
        try {
            $this->connection->pdo;
        } catch(Exception $e) {
            printf("Failed to connect to database with DSN %s\n", $this->connection->dsn);
            return false;
        }
        if(!$this->connection->tableExists($this->migrationTable)) {
            if($this->confirm!='yes' && !CliHelper::confirm("The `$this->migrationTable` table does not exist. Create?")) exit;
            echo "Creating `migration` table...", PHP_EOL;
            $this->connection->pdo->query('CREATE TABLE '.$this->migrationTable.' (name varchar(100) unique primary key, applied int)');
            if(!$this->connection->tableExists($this->migrationTable)) {
                echo "Creating `$this->migrationTable` table failed", PHP_EOL;
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
	/**
	 * @return bool -- must return true on success
     */
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

	/**
	 * Delete the database and migrate up from the beginning
	 *
	 * @return int -- error status
	 * @throws Exception
	 */
	public function actionReset() {
		if($this->confirm!='yes' && !CliHelper::confirm('Are you sure to delete the whole database?')) return 1;
		$name = $this->connection->schemaName;
		if($this->verbose) echo "Deleting database '$name'...\n";
		if(!$this->truncateDatabase()) {
			echo "Error truncating database", PHP_EOL;
			return 2;
		}
		if(!$this->createMigrationTable()) return 3;
		return $this->actionUp();
	}

	/**
	 * Drop all object from the database
	 *
	 * @param int $verbose
	 * @return bool -- success
	 * @throws Exception
	 */
	public function truncateDatabase($verbose=0) {
		$metadata = $this->connection->schemaMetadata;
		$success = true;

		// First drop all foreign keys,
		if($verbose>1) echo "Dropping foreign keys...\n";
		foreach ($metadata as $tableName=>$tableData) {
			$foreignKeys = $this->connection->getForeignKeys($tableName);
			if($foreignKeys) {
				foreach ($foreignKeys as $name => $foreignKey) {
					if($verbose>2) echo "Dropping foreign key '$name'\n";
					$constraint_name = $foreignKey['CONSTRAINT_NAME']??$foreignKey['constraint_name'];
					$table_name = $foreignKey['TABLE_NAME']??$foreignKey['table_name'];
					if(!$this->connection->dropForeignKey($constraint_name, $table_name)) $success=false;
					elseif($verbose>1) echo "Foreign key $name dropped.\n";
				}
			}
		}

		// drop the tables:
		if($verbose>1) echo "Dropping tables and views...\n";
		foreach ($metadata as $tableName => $schema) {
			if($verbose>2) echo "Dropping table/view '$tableName'\n";
			try {
				$this->connection->dropTable($tableName);
				if($verbose>1) echo "Table $tableName dropped.\n";
			} catch (Exception $e) {
				if ($this->connection->isViewRelated($message = $e->getMessage())) {
					if(!$this->connection->dropView($tableName)) $success=false;
					elseif($verbose>1) echo "View $tableName dropped.\n";
				} else {
					echo "Cannot drop table '$tableName': $message .\n";
					$success = false;
				}
			}
		}

		// Drop functions from public schema (exclude pg_catalog)
		$functions = $this->connection->getRoutines();
		if($verbose>1 && $functions) echo "Dropping functions and routines\n";
		foreach($functions as $functionName) {
			if($verbose>2) echo "Dropping $functionName\n";
			if($this->connection->dropRoutine($functionName)) {
				if($verbose>1) echo "$functionName dropped.\n";
			}
			else {
				echo "Cannot drop '$functionName'.\n";
				$success = false;
			}
		}

		// TODO: drop triggers
		// $triggers = $this->connection->getTriggers();

		// Drop sequences // Note: MySQL does not use explicit sequences, this is for future compatibility (e.g. PostgreSQL)
		$sequences = $this->connection->getSequences();
		if($verbose>1 && $sequences) echo "Dropping sequences...\n";
		foreach($sequences as $sequenceName) {
			if($verbose>2) echo "Dropping sequence $sequenceName\n";
			if(!$this->connection->dropSequence($sequenceName)) $success=false;
			elseif($verbose>1) echo "Sequence $sequenceName dropped.\n";
		}

		return $success;
	}

	/**
	 * Waits for database (container) to be ready for connection
	 *
	 * See also: tests/docker/readme.md
	 *
	 * @param int $timeout -- seconds to giving up
	 * @param int $interval -- seconds between connection attempts
	 * @return int -- 0 on success, 1 otherwise
	 */
	public function actionWait($timeout=60, $interval=5) {
		$result = AppHelper::waitFor(function() use ($interval) {
			try {
				echo "Trying to connect...\n";
				if(!$this->app->db->pdo) return false;
				echo "Connected\n";
				return true;
			}
			catch(Throwable $e) {
				echo $e->getMessage()."\n";
				return false;
			}
				/** @noinspection PhpWrongCatchClausesOrderInspection */
			catch(Exception $e) {
				echo $e->getMessage()."\n";
				return false;
			}
		}, $timeout, $interval);
		return $result ? 0 : 1;
	}
}
