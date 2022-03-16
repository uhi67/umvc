<?php

namespace uhi67\umvc;

use Exception;
use PDO;

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
     * @throws Exception on failure
     */
    public function reset() {
        // Set MySQL to SQL-92 standard tableName/fieldName quoting. The backtick quoting is still valid
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
}
