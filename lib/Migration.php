<?php /** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnused */

namespace uhi67\umvc;

use Exception;
use Throwable;

/**
 * Base class for php migrations, and helper for migrate command.
 *
 * ## Usage
 *
 * ### Create descendant migration classes in the migration directory using CLI:
 *
 *        php app migrate create <name>
 *
 * Then, fill in the 'up()' method with the migration job. You may access the database through `$this->connection` property.
 *
 * ### Migrate the database up to the current state:
 *
 *        php command/migrate.php
 *
 * ### Migrate down to any commit:
 *
 *        1. Delete the database completely (all data will be lost)
 *        2. Migrate up to the current statue `php command/migrate.php`
 *
 * ## Options:
 *
 *        - verbose=n    -- 0=silent, 1=normal(default), 2=more details
 *        - confirm=yes    -- override confirmation questions
 *
 * If a migration fails, the failed migration will be rolled back and no further migrations will be applied from the queue.
 *
 * ## Migration file formats
 *
 * Migration files in the migrations directory are executed by name order. The standard name convention is `mYMD_His_name.ext`
 *
 * ### 1. SQL format
 *
 * A plain text file containing a single SQL command. The command may be terminated by ';'
 * Lines beginning with -- and sections between /* and _*_/ are comments
 * The SQL command is executed via `PDO::query()`
 *
 * ### 2. php format
 *
 * A php class extending Migration. May be created by the `php app migrate create name` command.
 * The class must have a public function `up()`. This function performs a migration to the current state.
 * The migration is enclosed in a mySQL transaction, therefore, if any part of it fails, the entire migration step will be rolled back.
 *
 * In the method you may use:
 * - `$this->executeSqlFile()` to execute an SQL file containing multiple SQL commands.
 * - any method of the db connection via PDO class using `$this->connection->...` e.g. `$this->connection->query()`
 *  (In fact, `$this->connection` is currently a non-standard equivalent of `static::$connection`, but please use `$this->connection` for the future compatibility)
 *
 * @property-read Connection $connection
 * @package UMVC Simple Application Framework
 */
abstract class Migration extends Component
{
    /** @var Connection $connection */
    public Connection $connection;
    public int $verbose = 0;

    /** @var App $app */
    public App $app;

    /**
     * This method must do the migration up job in the migration class.
     *
     * @return bool -- must return true on success, false on failure
     * @throws Exception -- may throw an Exception on failure
     */
    abstract public function up(): bool;

    /**
     * @param string $cmd -- the SQL command to execute
     * @param array $replacements [[pattern, replace], ...]
     * @param string $filename -- the source file name to display on error
     * @param int $line -- source line where this SQL command begins
     * @return bool -- success
     * @throws Exception
     * @throws Throwable
     */
    private function execSingle(string $cmd, array $replacements, string $filename, int $line): bool
    {
        if (!empty($replacements)) {
            foreach ($replacements as $pair) {
                if (is_array($pair) && isset($pair[0]) && isset($pair[1])) {
                    $cmd = str_replace($pair[0], $pair[1], $cmd);
                }
            }
        }
        /** @var array $skipPatterns SQL statements to skip execute (regExp patterns) */
        $skipPatterns = [
            '/^CREATE\s+DATABASE/',
            // database must be created manually
            '/^USE\s/',
            // not supported in a prepared statement (not needed anyway)
            '/^START TRANSACTION/',
            // not supported in a prepared statement (a migration step is wrapped in a transaction anyway)
        ];
        foreach ($skipPatterns as $skipPattern) {
            if (preg_match($skipPattern, $cmd)) {
                if ($this->verbose) {
                    echo "SQL statement at line  $line is skipped:", PHP_EOL, $cmd, PHP_EOL;
                }
                return true; // Not considered as a failure
            }
        }
        if ($this->verbose > 2) {
            echo "--- Executing SQL command at line $line:", PHP_EOL, $cmd, PHP_EOL;
        }
        try {
            $params = $this->connection->params ?? [];
            foreach ($params as $key => $value) {
                if(!str_contains($cmd, sprintf(':%s', $key))) unset($params[$key]);
            }
            $stmt = $this->connection->pdo->prepare($cmd);
            $result = $stmt && $stmt->execute($params);
        } catch (Throwable $e) {
            App::log(
                'error',
                sprintf(
                    "Exception '%s' in file %s at line %s occurred at SQL '%s' in file %s at line %s",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $cmd,
                    $filename,
                    $line
                )
            );
            throw $e;
        }
        if ($result === false) {
            $error = $this->connection->lastError;
            echo "SQL Command: $cmd", PHP_EOL;
            echo "Query failed at line $line in file $filename", PHP_EOL;
            echo $error, PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * Executes an external SQL file
     *
     * If the file does not exist, an error message will be written out, and false will be returned.
     *
     * Executes SQL commands separated by ';'
     * Multiline comments are not supported! (parser will fail)
     *
     * @param string $filename
     * @param array $replacements [[pattern, replace], ...]
     *
     * @return bool
     * @throws Throwable
     */
    public function executeSqlFile(string $filename, array $replacements = []): bool
    {
        if (!file_exists($filename)) {
            echo "Migration SQL file '$filename' is missing", PHP_EOL;
            return false;
        }
        if ($this->verbose > 1) {
            echo "Executing SQL file '$filename", PHP_EOL;
        }

        // Executing separated by ';'
        // Multiline comments are not supported!
        $command = file($filename);
        $cmd = '';
        $i = 0;
        foreach ($command as $i => $line) {
            if (str_starts_with(trim($line), '--')) {
                continue;
            }
            $cmd .= $line;
            if (str_ends_with(trim($line), ';')) {
                if (!static::execSingle($cmd, $replacements, $filename, $i)) {
                    return false;
                }
                $cmd = '';
            }
        }
        $cmd = trim($cmd);
        if ($cmd) {
            if (!static::execSingle($cmd, $replacements, $filename, $i)) {
                return false;
            }
        }
        return true;
    }
}
