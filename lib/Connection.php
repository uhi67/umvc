<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use Exception;
use PDO;

/**
 * Low-level database functions, based on PDO.
 *
 * Vendor-specific drivers must be derived from this class.
 * Use the high-level Model database functions instead of this.
 *
 * ### Low-level example
 * ```php
 *     $c = Connection::connect('mysql:host=localhost;dbname=mvc', 'mvc', 'password');
 *     $stmt = $c->pdo->query('select * from mode order by id');
 *     if($stmt) $result = $stmt->fetchAll(PDO::FETCH_NUM);
 *     else echo 'Error: '.$c->lastError;
 * ```
 *
 * @property-write $user
 * @property-write $password
 *
 * @property-read $lastError -- "ANSI-code; driver-code; driver-message"
 * @property-read $schemaName
 * @property-read $schemaMetadata -- metadata array indexed by table names
 * @property-read array[]|bool $foreignKeys
 * @property-read string[] $routines
 * @property-read string[] $sequences
 * @property-read string[] $tables
 * @property-read string[] $triggers -- trigger names as schema.name
 * @property-read PDO $pdo -- the PDO connection (calling it connects immediately)
 *
 * @package UMVC Simple Application Framework
 */
abstract class Connection extends Component {
    /** @var string $dsn -- the DSN was used to create this connection */
    public $dsn;
    /** @var string $name -- database name (redundant, but mandatory) */
    public $name;
    /** @var string|null $migrationPath -- directory of migration files if not the default (`/migrations`)*/
    public $migrationPath = null;
    /** @var string|null $migrationTable -- name of the migration table if not the default (`migration`) */
    public $migrationTable;

    /** @var PDO|null $_pdo -- the original PDO connection */
    public $_pdo = null;

    protected $_user, $_password;

    /**
     * Must reset the connection to the standard state (e.g. Quote mode)
     *
     * @return bool
     * @throws Exception
     */
    abstract public function reset();

	/**
	 * Check if a table exists in the current database.
	 *
	 * @param $tableName
	 * @return bool -- TRUE if table exists, FALSE if no table found.
	 * @throws Exception
	 */
    public function tableExists($tableName) {
        $tableName = $this->quoteIdentifier($tableName);
        // Try a select statement against the table
        // Run it in try-catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $result = $this->pdo->query("SELECT 1 FROM $tableName LIMIT 1");
        } catch (Exception $e) {
            // We got an exception (table not found)
            return FALSE;
        }

        // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return $result !== FALSE;
    }

	/**
	 * Make table or fieldName safe from SQL injections (SQL-92 standard)
	 * Works on a single identifier. To handle schema.table.field constructs, see {@see Query::quoteFieldName()}
	 * Encloses into double quotes, inner double quotes are replaced by _
	 * May be overridden in vendor-specific way.
	 *
	 * @param string $fieldName
	 * @return string
	 *
	 * @throws Exception
	 */
    public function quoteIdentifier(string $fieldName) {
        if(!$fieldName) throw new Exception('Empty field-name');
        if($fieldName[0]=='"' && substr($fieldName, -1) == '"') $fieldName = substr($fieldName,1,-1);
        return '"'.str_replace('"', '_', $fieldName).'"';
    }

    /**
     * Make the value safe from SQL injections (MySQL-specific)
     *
     * @param int|string $value
     * @return string -- the value with necessary single quotes (except integers and NULL)
     */
    public function quoteValue($value) {
        if(is_integer($value) || is_numeric($value)) return $value;
        if(is_null($value)) return 'NULL';
        return $this->pdo->quote($value);
    }

    /**
     * Replaces common operator names to vendor-specific version
     * The default implementation does not change the name
     */
    public function operatorName($op) {
        return $op;
    }

    /**
     * @return string -- "ANSI-code; driver-code; driver-message" or "00000; 0; " if no error.
     */
    public function getLastError() {
        return implode('; ', $this->pdo->errorInfo());
    }

    /**
     * Must determine if SQL server supports NULLS LAST option in ORDER BY clause
     * @return boolean
     */
    abstract public function supportsOrderNullsLast();

    /**
     * Returns metadata of all tables indexed by table name
     *
     * @return array|false -- metadata array indexed by table names
     * @throws Exception
     */
    public function getSchemaMetadata() {
        $tables = $this->getTables();
        return array_combine($tables, array_map(function($tableName) {
            return static::tableMetadata($tableName);
        }, $tables));
    }

    /**
     * Returns array of table names (without schema prefix)
     *
     * @param string|null $schema
     *
     * @return string[]
     * @throws Exception
     */
    public function getTables($schema=null) {
        if(!$schema) $schema = $this->getSchemaName();
        /** @noinspection SqlResolve */
        $stmt = $this->pdo->query($sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema='.$this->pdo->quote($schema));
        if(!$stmt) throw new Exception(implode(';', $this->pdo->errorInfo()) . $sql);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        return array_column($rows, 0);
    }

	/**
	 * Returns metadata of the table
	 *
	 * (Associative to field names)
	 * Vendor-specific, this implementation is for MySQL only.
	 *
	 * @param string $table
	 * @return array|boolean -- returns false if table does not exist
	 * @throws Exception
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
     * Return dbname part of the DSN string, or empty string if dbname part not found
     *
     * @return string
     * @throws Exception
     */
    public function getSchemaName() {
        /** @noinspection PhpUnhandledExceptionInspection */
        return AppHelper::substring_before(AppHelper::substring_after($this->dsn, 'dbname='), ';', true);
    }

    public function setUser($user) { $this->_user = $user; }
    public function setPassword($password) { $this->_password = $password; }

	/**
	 * Creates a new connection using vendor driver specified in the DSN
	 *
	 * Note: this method is not suitable for configuration array, only to create new ad-hoc connections.
	 *
	 * @throws Exception -- if driver is missing for DSN vendor or vendor is not set in the DSN
	 * @return Connection -- the new Connection created
	 */
	public static function connect($dsn, $user, $password) {
		if(!$dsn) throw new Exception('DSN is not set');
		$vendor = AppHelper::substring_before($dsn, ':');
		if(!$vendor) throw new Exception('Invalid DSN: vendor is not set');
		$driver = 'app\lib\\'.ucfirst(strtolower($vendor)).'Connection';
		if(!class_exists($driver)) throw new Exception('No database driver for '.$vendor);

		return new $driver([
			'dsn' => $dsn,
			'user' => $user,
			'password' => $password
		]);
	}

	/**
	 * Returns foreign keys information of the table as
	 *
	 * 	[
	 * 		'constraint_name' => [
	 * 			'column_name' => 'columnName',
	 * 			'foreign_schema' => 'schemaName',
	 * 			'foreign_table' => 'tableName'
	 * 			'foreign_column' => 'columnName'
	 * 		],
	 * 		...
	 *  ]
	 *
	 * @param string $tableName -- may contain schema prefix
	 * @param string|null $schema -- optional (table prefix overrides; default is current schema)
	 *
	 * @return array|bool
	 */
	abstract public function getForeignKeys($tableName, $schema=null);

	/**
	 * Returns remote foreign keys referred to this table (reverse foreign key)
	 *
	 * 	[
	 * 		'constraint_name' => [
	 * 			'remote_schema' => 'schemaName',
	 * 			'remote_table' => 'tableName'
	 * 			'remote_column' => 'columnName'
	 * 			'table_schema' => 'columnName',
	 * 			'table_name' => 'columnName',
	 * 			'column_name' => 'columnName',
	 *          'constraint_name' => 'constraint_name',
	 * 		],
	 * 		...
	 *  ]
	 *
	 * @param string $tableName -- may contain schema prefix
	 * @param string|null $schema -- optional (table prefix overrides; default is current schema)
	 *
	 * @return array|bool
	 */
	abstract public function getReferrerKeys($tableName=null, $schema=null);

	/**
	 * @param string $tableName
	 * @param string|null $schema
	 *
	 * @return false|resource
	 */
	abstract public function dropTable($tableName, $schema=null);

	/**
	 * @param string $viewName
	 * @param string|null $schema
	 *
	 * @return false|resource
	 */
	abstract public function dropView($viewName, $schema=null);

	/**
	 * Returns sequence names
	 *
	 * @param string|null $schema
	 *
	 * @return false|resource
	 */
	abstract public function getSequences($schema=null);

	/**
	 * @param string $sequenceName
	 * @param string|null $schema
	 *
	 * @return false|resource
	 */
	abstract public function dropSequence($sequenceName, $schema=null);

	/**
	 * Returns array with function names followed by parameter list and preceded with routine type from the give schema.
	 *
	 * @param string $schema -- default is "public"
	 * @return string[]
	 */
	abstract public function getRoutines($schema=null);

	/**
	 * @param string $routineName
	 * @param string $routineType
	 * @param string|null $schema
	 *
	 * @return resource|false -- success
	 */
	abstract public function dropRoutine($routineName, $routineType='FUNCTION', $schema=null);

	/**
	 * Returns array with trigger names
	 *
	 * @param $schema
	 * @return string[] -- trigger names as schema.name
	 */
	abstract public function getTriggers($schema=null);

	/**
	 * Determines whether the error message is related to deleting a view or not
	 * @param string $errorMessage
	 * @return bool
	 */
	public function isViewRelated($errorMessage) {
		$dropViewErrors = [
			'DROP VIEW to delete view', // SQLite
			'SQLSTATE[42S02]', // MySQL
		];

		foreach ($dropViewErrors as $dropViewError) {
			if (strpos($errorMessage, $dropViewError) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the current PDO connection. Will be created now if it is not yet connected.
	 *
	 * @return PDO -- A PDO object if connected
	 * @throws Exception -- If connection failed
	 */
	public function getPdo() {
		if(!$this->_pdo) {
			$this->_pdo = new PDO($this->dsn, $this->_user, $this->_password);
			$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->reset();
		}
		return $this->_pdo;
	}
}
