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
     * Returns metadata of the table
     *
     * (Associative to field names)
     * Vendor-specific, this implementation is for MySQL only.
     *
     * @param string $table
     * @return array|boolean -- returns false if table does not exist
     */
    public function tableMetadata($table) {
        $table = $this->quoteIdentifier($table);
        $stmt = $this->pdo->query('show fields from '.$table);
        if(!$stmt) return false;
        $rows = $stmt->fetchAll();
        if($rows===false) return false;
        $i = 1;
        $result = array();
        foreach($rows as $row) {
            $type = $row['Type'];
            $len = -1;
            if(preg_match('/(\w+)\((\d+)\)/', $type, $mm)) {
                $type = $mm[1];
                $len = (int)$mm[2];
            }
            $result[$row['Field']] = [
                'num' => $i++,
                'type' => $type,
                'len' => $len,
                'not null' => $row['Null']=='NO',
                'has default' => $row['Default']!==null,
            ];
        }
        return $result;
    }

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
