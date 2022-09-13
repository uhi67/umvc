<?php

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

    /**
     * @throws Exception
     */
    public function init() {
        $this->pdo = new PDO($this->dsn, $this->_user, $this->_password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->reset();
    }

    public function supportsOrderNullsLast() { return false; }

    /**
     * Resets the connection to standard mode.
     * Set MySQL to SQL-92 standard tableName/fieldName quoting. The backtick quoting is still valid
     *
     * @throws Exception on failure
     */
    public function reset() {
        $status = $this->pdo->query("SET SQL_MODE='ANSI_QUOTES,NO_AUTO_VALUE_ON_ZERO'");
        if($status===false) throw new Exception("Error resetting the connection. ".implode(';', $this->pdo->errorInfo()));
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
    public function quoteIdentifier($fieldName) {
        if(!$fieldName) throw new Exception('Empty field-name');
        if($fieldName[0]=='`' && substr($fieldName, -1) == '`') $fieldName = substr($fieldName,1,-1);
        return '`'.str_replace('`', '_', $fieldName).'`';
    }

	/**
	 * @param string $tableName
	 * @param string|null $schema
	 * @return array
	 * @inheritDoc
	 * @throws Exception
	 */
	public function getForeignKeys($tableName, $schema=null) {
		if(!$schema) $schema = $this->name;
		$sql = /** @lang */"SELECT table_name, column_name, constraint_name, 
				referenced_table_name as foreign_table, 
				referenced_column_name as foreign_column 
			FROM information_schema.key_column_usage 
			WHERE
				referenced_table_name is not null and 
				table_name = $1 AND table_schema=$2";
		$stmt = $this->pdo->query($sql, [$tableName, $schema]);
		if(!$stmt) throw new Exception(implode(';', $this->pdo->errorInfo()) . $sql);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if(empty($rows)) return [];
		$rr = [];
		foreach($rows as $r) $rr[$r['constraint_name']] = $r;
		return $rr;
	}


	/**
	 * Drops the named constraint from the table
	 *
	 * @param string $constraintName
	 * @param string $tableName
	 * @param string|null $schema
	 *
	 * @return false|PDOStatement
	 * @throws Exception
	 */
	public function dropForeignKey($constraintName, $tableName, $schema = null) {
		if(!$schema) $schema = $this->name;
		// ALTER TABLE <tableName> DROP FOREIGN KEY <constraintName>
		if(!strpos($tableName, '.')) $tableName = $this->quoteIdentifier($schema).'.'.$this->quoteIdentifier($tableName);
		$constraintName = $this->quoteIdentifier($constraintName);
		return $this->pdo->query(/** @lang text */"ALTER TABLE $tableName DROP FOREIGN KEY $constraintName");
	}

	public function getReferrerKeys($tablename, $schema = null) {
		// TODO: Implement getReferrerKeys() method.
	}

	public function dropView($tableName, $schema = null) {
		// TODO: Implement dropView() method.
	}

	public function getSequences($schema = null) {
		// TODO: Implement getSequences() method.
	}

	public function dropSequence($sequenceName, $schema = null) {
		// TODO: Implement dropSequence() method.
	}

	public function getRoutines($schema = null) {
		// TODO: Implement getRoutines() method.
	}

	public function dropRoutine($routineName, $routineType = 'FUNCTION', $schema = null) {
		// TODO: Implement dropRoutine() method.
	}

	public function dropTable($tableName, $schema = null) {
		// TODO: Implement dropTable() method.
	}
}
