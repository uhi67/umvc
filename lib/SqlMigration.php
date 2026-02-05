<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnused */

namespace educalliance\umvc;

use Exception;
use Throwable;

/**
 * SQL type migration
 *
 * ## Usage
 *
 * A plain text file containing a single SQL command. The command may be terminated by ';'
 * Lines beginning with -- and sections between /* and _*_/ are comments
 * The SQL command is executed via `PDO::query()`
 *
 * @property-read Connection $connection
 * @package UMVC Simple Application Framework
 */
class SqlMigration extends Migration
{
    public string $filename;

    /**
     * This method must do the migration up job in the migration class.
     *
     * @return bool -- must return true on success, false on failure
     * @throws Exception -- may throw an Exception on failure
     * @throws Throwable
     */
    public function up(): bool
    {
        return $this->executeSqlFile($this->filename);
    }
}
