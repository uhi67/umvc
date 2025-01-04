<?php /** @noinspection PhpUnusedPrivateMethodInspection */

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;
use PDO;
use PDOStatement;

/**
 * Connection class for MySQL databases
 *
 * @property-write $user
 * @property-write $password
 * @package UMVC Simple Application Framework
 */
class MysqlConnection extends Connection {
    public function supportsOrderNullsLast(): bool {
        return false;
    }

    /**
     * Resets the connection to standard mode.
     * Set MySQL to SQL-92 standard tableName/fieldName quoting. The backtick quoting is still valid.
     *
     * @throws Exception on failure
     */
    protected function reset(): bool {
        // Note: _pdo must be used here, because reset() is used within pdo getter.
        $status = $this->_pdo->query("SET SQL_MODE='ANSI_QUOTES,NO_AUTO_VALUE_ON_ZERO'");
        if ($status === false) throw new Exception("Error resetting the connection. " . implode(';', $this->_pdo->errorInfo()));
        return true;
    }

    /**
     * Make table or fieldName safe from SQL injections
     * Works on a single identifier. To handle schema.table.field constructs, see {@see Query::quoteFieldName()}
     *
     * MySQL encloses into backticks, inner backticks are replaced by _.
     *
     * @param string $fieldName
     * @return string
     * @throws Exception
     */
    public function quoteIdentifier(string $fieldName): string {
        if (!$fieldName) throw new Exception('Empty field-name');
        if ($fieldName[0] == '`' && str_ends_with($fieldName, '`')) $fieldName = substr($fieldName, 1, -1);
        return '`' . str_replace('`', '_', $fieldName) . '`';
    }

    /**
     * Returns all foreign keys or foreign keys of a given a table and/or schema.
     *
     * @param string|null $tableName
     * @param string|null $schema
     * @return array[] constraint data indexed by schema.table.constraint_name
     * @inheritDoc
     * @throws Exception
     */
    public function getForeignKeys(string $tableName=null, string $schema = null): bool|array {
        if (!$schema) $schema = $this->name;
        $sql = /** @lang */
            "SELECT table_schema, table_name, column_name, constraint_name, 
				referenced_table_name as foreign_table, 
				referenced_column_name as foreign_column 
			FROM information_schema.key_column_usage 
			WHERE
				referenced_table_name is not null and 
				(:tableName is null OR table_name = :tableName) AND (:schema is null OR table_schema= :schema)";
        /** @noinspection DuplicatedCode */
        $rows = $this->queryAll($sql, ['tableName' => $tableName, 'schema' => $schema]);
        if (empty($rows)) return [];
        $rr = [];
        foreach ($rows as $r) {
            $index = ($r['TABLE_SCHEMA'] ?? $r['table_schema']) . '.' . ($r['TABLE_NAME'] ?? $r['table_name']) . '.' . ($r['CONSTRAINT_NAME'] ?? $r['constraint_name']);
            $rr[$index] = $r;
        }
        return $rr;
    }


    /**
     * Drops the named constraint from the table.
     * Syntax: ALTER TABLE <tableName> DROP FOREIGN KEY <constraintName>
     *
     * @param string $constraintName
     * @param string $tableName
     * @param string|null $schema
     *
     * @return false|PDOStatement
     * @throws Exception
     */
    public function dropForeignKey(string $constraintName, string $tableName, string $schema = null): bool|PDOStatement {
        if (!$schema) $schema = $this->name;
        $tableName = $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($tableName);
        $constraintName = $this->quoteIdentifier($constraintName);
        return $this->pdo->query(/** @lang text */ "ALTER TABLE $tableName DROP FOREIGN KEY $constraintName");
    }

    /**
     * @param string|null $tableName
     * @param string|false $schema
     * @return array[] constraint data indexed by constraint_name as schema.table.constraint
     * @inheritDoc
     * @throws Exception
     */
    public function getReferrerKeys(string $tableName = null, string|null $schema = null): bool|array {
        if ($schema === null) $schema = $this->name;
        $sql = /** @lang */
            "SELECT table_schema, table_name, column_name, constraint_name,
				referenced_table_schema as foreign_schema, 
				referenced_table_name as foreign_table, 
				referenced_column_name as foreign_column 
			FROM information_schema.key_column_usage 
			WHERE (referenced_table_name = :tableName OR :tableName is null) AND (referenced_table_schema = :schema OR :schema is null)";

        $rows = $this->queryAll($sql, ['tableName' => $tableName, 'schema' => $schema]);

        if (empty($rows)) return [];
        $rr = [];
        foreach ($rows as $r) {
            $index = ($r['TABLE_SCHEMA'] ?? $r['table_schema']) . '.' . ($r['TABLE_NAME'] ?? $r['table_name']) . '.' . ($r['CONSTRAINT_NAME'] ?? $r['constraint_name']);
            $rr[$index] = $r;
        }
        return $rr;
    }

    /**
     * @param string $viewName
     * @param string|null $schema
     * @return false|PDOStatement
     * @throws Exception
     */
    public function dropView(string $viewName, string|null $schema = null): false|PDOStatement {
        if ($schema === null) $schema = $this->name;
        $viewName = $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($viewName);
        return $this->pdo->query(/** @lang text */ "DROP VIEW $viewName");
    }

    /**
     * Returns sequence names as table.field
     * @param string|null $schema
     * @return string[]
     */
    public function getSequences(string|null $schema = null): array {
        if ($schema === null) $schema = $this->name;
        $sql = "select TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME from information_schema.columns where extra like '%auto_increment%' and TABLE_SCHEMA=:schema";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['schema' => $schema]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($item) {
            return $item['TABLE_NAME'] . '.' . $item['COLUMN_NAME'];
        }, $result);
    }

    public function dropSequence(string $sequenceName, string|null $schema = null): bool|PDOStatement {
        return true;
    }

    /**
     * Returns routine names as "type schema.name"
     *
     * @param string|null $schema -- default is false (= current schema). Specify null for all schemas
     * @return string[]
     * @throws Exception
     */
    public function getRoutines(string|null $schema = null): array {
        if ($schema === null) $schema = $this->name;
        // SELECT routine_name, routine_type, routine_schema FROM information_schema.routines WHERE routine_schema = 'umvc_test';
        $params = ['schema' => $schema];
        $sql = 'SELECT routine_name, routine_type, routine_schema FROM information_schema.routines WHERE routine_schema = :schema OR :schema is null';
        return array_map(function ($routine) {
            return $routine['routine_type'] . ' ' . $routine['routine_schema'] . '.' . $routine['routine_name'];
        }, $this->queryAll($sql, $params));
    }

    /**
     * @param string $routineName -- without quotes and schema name, and optional type prefix
     * @param string $routineType -- routine type, default is FUNCTION, name prefix overrides
     * @param string|null $schema
     * @return false|PDOStatement
     * @throws Exception
     */
    public function dropRoutine(string $routineName, string $routineType = 'FUNCTION', string|null $schema = null): bool|PDOStatement {
        if ($schema === null) $schema = $this->name;
        //	'DROP '||routine_type||' IF EXISTS '||routine_name'
        if (strpos($routineName, ' ')) [$routineType, $routineName] = explode(' ', $routineName, 2);
        $routineName = $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($routineName);
        $routineType = strtoupper($routineType);
        if (!in_array($routineType, ['FUNCTION', 'PROCEDURE'])) throw new Exception("Invalid routine type '$routineType'");
        $sql = 'DROP ' . $routineType . ' IF EXISTS ' . $routineName;
        return $this->pdo->query($sql);
    }

    /**
     *
     * Drops a table, return the result as a PDOStatement
     *
     * @param string $tableName -- without quotes and schema name
     * @param string|null $schema
     * @return false|PDOStatement -- false on failure
     * @throws Exception
     */
    public function dropTable(string $tableName, null|string $schema = null): false|PDOStatement {
        if ($schema === null) $schema = $this->name;
        $tableName = $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($tableName);
        return $this->pdo->query(/** @lang text */ "DROP TABLE IF EXISTS $tableName");
    }

    /**
     * @param string|null $schema
     * @return string[] -- trigger names as schema.name
     * @throws Exception
     */
    public function getTriggers(string $schema = null): array {
        if ($schema === false) $schema = $this->name;
        $sql = "SELECT trigger_schema, trigger_name FROM information_schema.triggers WHERE trigger_schema = :schema OR :schema is null";
        $result = $this->queryAll($sql, ['schema' => $schema]);
        if (!$result) return [];
        return array_map(function ($item) {
            return $item['trigger_schema'] . '.' . $item['trigger_name'];
        }, $result);
    }

    /**
     * Prepares, binds and executes a query and fetches all rows
     *
     * @param string $sql
     * @param array $params
     * @param int $mode
     * @return array
     * @throws Exception
     * @noinspection PhpSameParameterValueInspection
     */
    private function queryAll(string $sql, array $params = [], int $mode = PDO::FETCH_ASSOC): array {
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) throw new Exception(implode(';', $this->pdo->errorInfo()) . $sql);
        $stmt->execute($params);
        return $stmt->fetchAll($mode);
    }

    /**
     * Executes a non-select SQL statement with optional parameters
     *
     * @param string $sql
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) throw new Exception(implode(';', $this->pdo->errorInfo()) . $sql);
        return $stmt->execute($params);
    }
}
