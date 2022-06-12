<?php

namespace uhi67\umvc;

use Exception;
use PDO;

/**
 * Connection class for Sqlite databases
 *
 * @property-write $user
 * @property-write $password
 * @package UMVC Simple Application Framework
 */
class SqliteConnection extends Connection {
    public $autoCreate;

    /**
     * @throws Exception
     */
    public function init() {
//        $fileName = strchr($this->dsn, ':');
//        if(!file_exists($fileName) && $this->autoCreate)
        echo $this->dsn, PHP_EOL;
        $this->pdo = new PDO($this->dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->reset();
    }

    public function supportsOrderNullsLast() { return false; }

    /**
     * @throws Exception on failure
     */
    public function reset() {
        return true;
    }

    /**
     * Make table or fieldName safe from SQL injections
     * Works on a single identifier. To handle schema.table.field constructs, see {@see Query::quoteFieldName()}
     *
     * @param string $fieldName
     * @return string
     * @throws Exception
     */
    public function quoteIdentifier($fieldName) {
        if(!$fieldName) throw new Exception('Empty field-name');
        if($fieldName[0]=='"' && substr($fieldName, -1) == '"') $fieldName = substr($fieldName,1,-1);
        return '"'.str_replace('"', '_', $fieldName).'"';
    }

    /**
     * Returns metadata of the table
     *
     * (Associative to field names)
     * Vendor-specific, this implementation is for MySQL only.
     *
     * @param string $table
     * @return array|boolean -- returns false if table does not exist
     */
    public function tableMetadata($table)
    {
        $table = $this->quoteIdentifier($table);
        $stmt = $this->pdo->query('pragma table_info('.$table.')'); // Returns cid,name,type,notnull,dflt_value,pk
        if(!$stmt) return false;
        $rows = $stmt->fetchAll();
        if($rows===false) return false;
        $i = 1;
        $result = array();
        foreach($rows as $row) {
            $type = $row['type'];
            $len = -1;
            if(preg_match('/(\w+)\((\d+)\)/', $type, $mm)) {
                $type = $mm[1];
                $len = (int)$mm[2];
            }
            $result[$row['name']] = [
                'num' => $i++,
                'type' => $type,
                'len' => $len,
                'not null' => $row['notnull']==0,
                'has default' => $row['dflt_value']!==null,
                'pk' => $row['pk']==1,
            ];
        }
        return $result;
    }

    /**
     * Sqlite preparation
     */
    public function prepareStatement($sql, $options) {
        // Sqlite seems to have problems with this option when the SQL is parametric
        unset($options[PDO::ATTR_CURSOR]);
        return $this->pdo->prepare($sql, $options);
    }
}
