<?php

namespace uhi67\umvc;

use DateTime;
use Exception;
use PDO;
use PDOStatement;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Query builder
 *
 * The Query class represents an SQL query of any type, and builds the SQL statement when executed.
 * Query is strongly connected to the Model class, and needs all the models to be defined to build proper SQL statements.
 *
 *
 * @property string|Model $modelClass -- The model class to be returned as a SELECT result, or model to INSERT into or DELETE from
 * @property string $type -- the query type, default is SELECT. Other values: INSERT, UPDATE
 * @property Connection $connection
 * @property array $fields - select part of SELECT, or field list of INSERT. Array of literal field-names or other expressions
 * @property string|array $from -- Model class or model list for the FROM clause
 * @property array $joins  -- list of JOINS as [alias=>[model, join-type, condition, ...], ...] condition is a `mainField=>foreignField` associative pair, or any numeric-indexed other expression
 * @property array $where -- expression of WHERE conditions in SELECT, DELETE or UPDATE
 * @property array $orders
 * @property array $groups
 * @property int|null $limit
 * @property int|null $offset
 * @property array $values
 * @property array $params     -- the named parameters assigned by the user in {$name} form as [name=>value, ...], after build contains the auto-bound parameters as well
 * @property-read string $sql -- the SQL command built containing ? in place of all user parameters
 * @property-read int $affected -- number of rows affected by executing the last SQL statement
 *
 * // Despite these are not typical properties, these get*() methods without arguments also can be accessed as properties
 * @property-read int $count;
 * @property-read Model[]|array[] $all;
 * @property-read Model|array $one;
 * @property-read Model|array $column;
 * @property-read mixed $scalar
 *
 * Note: reading these fetcher properties may throw an Exception
 *
 * @package UMVC Simple Application Framework
 */
class Query extends Component
{
    const ORDER_ASC = 0;
    const ORDER_DESC = 1;
    const NULLS_FIRST = 0;
    const NULLS_LAST = 1;

    /**
     * @var array $_operators
     * Definitions of operators, in precedence order in form [operator name => operands, ...].
     * Special values:
     *  - 3: $1 op $2 AND $3
     *  - 4 means no limit, implode with pattern
     *  - 5: operator is after single operand
     *  - 6: 2 operands, second is an expression-list
     *  - 7: separate builder (might be vendor-specific)
     */
    protected static $_operators = [
        'null'	=>	0,
        'true'	=>	0,
        'false'	=>	0,
        '~'		=>	1,	// bitwise not
        '^'		=>	2,
        '*'		=>	4,
        '/'		=>	2,
        '%'		=>	2,
        '+'		=>	4,
        '-'		=>	2,
        '<<'	=>	2,
        '>>'	=>	2,
        '&'		=>	4,
        '|'		=>	4,
        '='		=>	2,
        '!='	=>	2,
        '<'		=>	2,
        '>'		=>	2,
        '>='	=>	2,
        '<='	=>	2,
        'is null'=>	5,
        'is not null'=>5,
        'is true'=>	5,
        'like'	=>	2,
        'ilike'	=>	2,
        'rlike'	=>	2, // ~* in pg
        'regex'	=>	2, // ~ in pg
        'not rlike'	=>	2, // !~* in pg
        'not regex'	=>	2, // !~ in pg
        'in'	=>	6,
        'not in'=>	6,
        'exists'=>  1,
        'between'=>	3,
        'not'	=>	1,
        'and'	=>	4,
        'or'	=>	4,
        '||'	=>	4,  // for string concatenation use concat()
        'interval' => 1,
        'current_timestamp' => 0,
        'case' => 7,
    ];

    /** @var string|Model $_modelClass -- The model class to be returned as a SELECT result, or model to INSERT into or DELETE from */
    private $_modelClass;
    /** @var bool $_indexField -- The result array must be indexed by the named field */
    private $_indexField;
    /** @var string $_type -- the query type, default is SELECT. Other values: INSERT, UPDATE */
    private $_type = 'SELECT';
    /** @var Connection|null $_connection -- the actual database connection, if not given, App's default is used */
    private $_connection;
    /** @var PDOStatement -- the last statement created by get*() */
    private $stmt;
    /** @var array $_fields - select part of SELECT, or field list of INSERT, if not given, * or indices of values are used. Ignored on UPDATE or DELETE. Array of literal field-names or other expressions */
    private $_fields;
    /** @var string|array|null|Model $_from  - model list (optionally indexed with aliases) of FROM part of SELECT or UPDATE or a single model name */
    private $_from;
    /** @var array $_joins -- -- list of JOINS as [alias=>[model, join-type, conditions], ...] condition is a `mainField=>foreignField` associative pair, or any numeric-indexed other expression */
    private $_joins;
    /** @var array|mixed $_where - expression of WHERE conditions in SELECT, DELETE or UPDATE */
    private $_where;
    /** @var array $_orders - expression list of ORDERS clause in SELECT. Does not use automatic alias of main table! */
    private $_orders;
    /** @var array $_groups - expression list of ORDERS clause in SELECT. Does not use automatic alias of main table! */
    private $_groups;
    /** @var int $_limit - LIMIT part of SELECT */
    private $_limit;
    /** @var int $_offset - OFFSET part of SELECT */
    private $_offset;
    /**
     * @var array|Query $_values - value list of VALUES part of insert or fieldName=>value pairs of UPDATE
     * UPDATE: fieldName=>expression syntax is used. Use ':' named parameter or explicit literals for literal values.
     * INSERT:
     *      - in case of _fields is null, for single-row insert the same syntax as UPDATE allowed.
     *      - if _fields contains field names, values are 2-dimensional array of literal values or a sub-Query. No expressions or parameters are allowed.
     */
    private $_values;
    /** @var array $_params -- The params the user specified, writable-only through params property */
    private $_params = [];
    private $_sql;
    /** @var array $_innerParams -- All parameters to be bound to the prepared statement -- read-only through params property */
    private $_innerParams = [];

    // The following values are stored values of the get*() fetcher functions of the current query until the query changes
    /** @var int $_count */
    private $_count;
    /** @var Model|array $_one */
    private $_one;
    /** @var array $_all */
    private $_all;
    /** @var scalar $_scalar */
    private $_scalar;
    /** @var array $_column */
    private $_column;

    /**
     * Generates a field-list based on model list
     *
     * @param array $models -- Model classes or subQueries
     * @param bool $forceAlias
     * @return array
     * @throws Exception
     */
    private function allModelFields(array $models) {
        $forceAlias = count($models) > 1;
        $fields = array();
        foreach($models as $key=>$modelName) {
            if($modelName instanceof Query) {
                // all fields from sub-query
                $fields = array_merge($fields, array($key.'.*'));
            } else {
                if(is_integer($key) && $forceAlias) $a1 = $modelName::tableName();
                else {
                    $a1 = $key;
                }
                $fields1 = $this->modelFields($modelName);
                if($a1) $fields1 = array_map(function($f) use ($a1) {
                    return $a1 . '.' . $f;
                }, $fields1);
                $fields = array_merge($fields, $fields1);
            }
        }
        return $fields;
    }

    /**
     * Make a (complex) fieldName safe from SQL injections
     *
     * @param string $fieldName -- quoted or unquoted field name with an optional table (-alias) prefix
     * @return string quoted field name with an optional quoted prefix
     * @throws Exception -- if the name contains the delimiter
     */
    public function quoteFieldName($fieldName) {
        if(strpos($fieldName, '.')!==false) {
            $db = $this;
            return implode('.', array_map(function($n) use($db) {return $db->quoteSingleName($n);}, explode('.', $fieldName)));
        }
        return $this->quoteSingleName($fieldName);
    }

    /**
     * Converts value to a valid literal value for SQL binding
     * Does not affect internal quotes!
     *
     * Use only in binding, not in SQL building.
     * In manually built SQL commands on literal values {@see Connection::quoteValue()} must be used.
     *
     * @param $value
     * @return string|integer -- value without quotes
     */
    public static function literal($value) {
        if(is_integer($value) || is_numeric($value)) return $value;
        if(is_null($value)) return null;
        if($value instanceof DateTime) return $value->format('Y-m-d H:i:s');
        return $value;
    }

    /**
     * Sets the SELECT part of the query.
     * Overwrites the previous list!
     *
     * Expresionlist may contain:
     * - simple fieldname
     * - alias=>fieldname
     * - alias=>expression (expression may be without alias, but not recommended)
     * - alias=>subQuery
     *
     * Example:
     *  ->select(['id, 'name'=>'fullname', 'sum'=>['count()', '*'], ...])
     *
     * @param array|null $expressionList -- Array of literal field-names or other expressions
     * @return $this
     */
    public function select($expressionList=null) {
        $this->_fields = $expressionList;
        $this->type = 'SELECT';
        $this->_sql = null;
        $this->invalidateResults();
        return $this;
    }

	/**
	 * Adds fields to the SELECT part of the query.
	 *
	 * (If no fields were defined earlier, all fields of all current models are used)
	 *
	 * @param array|null $expressionList -- Array of literal field-names or other expressions. Single field-name is allowed, but single expression must be wrapped in an array.
	 * @return $this
	 * @throws Exception
	 */
    public function addSelect($expressionList=null) {
        if($expressionList) {
            if(!$this->_fields) $this->_fields = $this->allModelFields($this->_from);
            $this->_fields = $this->_fields ? array_merge($this->_fields, (array)$expressionList) : (array)$expressionList;
            $this->type = 'SELECT';
            $this->_sql = null;
            $this->invalidateResults();
        }
        return $this;
    }

    /**
     * Sets the FROM part of the query
     * Overwrites the previous conditions!
     *
     * @param array|string $tableNameList
     * @return $this
     */
    public function from($tableNameList) {
        $this->from = $tableNameList;
        $this->_sql = null;
        $this->invalidateResults();
        return $this;
    }

    /**
     * Adds one or more JOIN clauses to the query. Chainable.
     * Alias is optional. Default the table name is used.
     *
     * For repeating table names you must supply unique aliases, unless the generated SQL will not be valid.
     *
     * @param array $joinList -- [alias=>[foreignModel, joinType, condition,...], ...]
     * @return Query
     */
    public function join($joinList) {
        if(empty($joinList)) return $this;
        if(isset($joinList[0]) && !is_array($joinList[0])) $joinList = [$joinList];
        $this->_joins = is_array($this->_joins) ? array_merge($this->_joins, $joinList) : $joinList;
        $this->_sql = null;
        $this->invalidateResults();
        return $this;
    }

    /**
     * Sets the WHERE part of the query with an expression
     * Overwrites the previous conditions!
     *
     * Expression may be:
     *
     *  - a single scalar literal for single pk
     *  - array of fieldName=>literalValue pairs (evaluated to parametrized query)
     *  - other expression array, see {@see Query::buildExpression()}
     *
     * @param mixed $expression
     * @return Query
     */
    public function where($expression, $params=[]) {
        $this->where = $expression;
        if(!empty($params)) $this->bind($params);
        $this->_sql = null;
        return $this;
    }

    /**
     * Adds a new condition to the WHERE part of the query
     * Concatenated with AND to the previous conditions!
     *
     *  - array of fieldName=>literalValue pairs (evaluated to parametrized query)
     *  - other expression array, see {@see Query::buildExpression()}
     *  - Unlike the where(), single literal string here considered as an expression, i.e. a field name (possibly boolean type)
     *
     * @param mixed $expression
     * @return Query
     */
    public function andWhere($expression, $params=[]) {
        if(empty($this->where)) {
            $this->_where = ['AND', $expression];
        } elseif(is_array($this->where) && isset($this->where[0]) && $this->where[0]=='AND') {
            $this->_where[] = $expression;
        } else {
            $this->_where = ['AND', $this->where, $expression];
        }

        $this->invalidateResults();
        $this->_sql = null;

        if(!empty($params)) $this->bind($params);
        $this->_sql = null;
        return $this;
    }

    /**
     * Sets the ORDER BY part of the SELECT query. Chainable.
     *
     * **orderExpressionList** array elements may be:
     *  - order definition array as [fieldName, order direction, nulls],
     *  - simple string beginning with '(' will be used literally,
     *  - all other simple string is a field name
     *  - order direction: ORDER_ASC (default), ORDER_DESC
     *  - nulls: NULLS_FIRST (default), NULLS_LAST (not supported by mySQL)
     *  - fieldName may be any expressions see {@see Query::buildExpression()}
     *
     * @param $orderExpressionList -- numeric indexed array of strings or [fieldName, order direction, nulls]
     * @return $this
     */
    public function orderBy($orderExpressionList) {
        $this->orders = $orderExpressionList;
        $this->_sql = null;
        return $this;
    }

    /**
     * Sets the LIMIT part of the SELECT query. Chainable.
     *
     * @param $value
     * @return $this
     */
    public function limit($value) {
        $this->limit = $value;
        return $this;
    }

    /**
     * Sets the OFFSET part of the SELECT query. Chainable.
     *
     * @param $value
     * @return $this
     */
    public function offset($value) {
        $this->offset = $value;
        return $this;
    }

    /**
     * Sets the field used for indexing the result records received by a getAll() call. Chainable.
     * May be set to null, in this case the result will not be indexed (default behaviour).
     * The index column must exist in the result set (must be selected).
     *
     * @param string|null $indexField
     * @return $this
     */
    public function indexedBy($indexField) {
        $this->_indexField = $indexField;
        $this->invalidateResults();
        return $this;
    }

    /**
     * Sets the GROUP BY part of a SELECT query, chainable.
     *
     * @param $groupBy
     * @return $this
     */
    public function groupBy($groupBy) {
        $this->_groups = $groupBy;
        $this->_sql = null;
        return $this;
    }

    /**
     * Sets the SET part of an UPDATE Query in chained mode. Changes query type to UPDATE
     * Example: Model::createQuery()->set(['name'=>'Foo'])->where(['id'=>$bar]);
     *
     * @param $values
     * @return $this
     */
    public function set($values) {
        $this->invalidateResults();
        $this->_type = 'UPDATE';
        $this->_values = $values;
        $this->_sql = null;
        return $this;
    }

    /**
     * Adds named parameters to the query. Don't use it for numeric-indexed parameters.
     * Provide an associative array of parameters, to be substituted in patterns like `:name`.
     * The new valueList will be merged with the existing, but same names will be overwritten.
     * For completely replace params values, see {@see setParams}.
     *
     * Chainable.
     *
     * @param array $valueList -- [$name=>$value, ...]
     * @return $this
     */
    public function bind($valueList) {
        if(!$this->_params) $this->_params = [];
        $this->params = array_merge($this->_params, $valueList);
        return $this;
    }

    // Getters and setters
    //---------------------

    public function setModelClass($modelClass) {
        $this->_modelClass = $modelClass;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }
    public function getModelClass() { return $this->_modelClass; }

    public function getIndexField() { return $this->_indexField; }

    public function setType($type) {
        $this->_type = $type;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }
    public function getType() { return $this->_type ?: 'SELECT'; }

    public function setFields($value) {
        $this->_fields = $value;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }

    public function getFields() {
        return $this->_fields;
    }

    public function setFrom($value) {
        $this->_from = $value;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }

    public function getFrom() {
        return $this->_from;
    }

    public function setJoins($joinList) {
        $this->_joins = $joinList;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }

    public function getJoins() {
        return $this->_joins;
    }

    public function setWhere($value) {
        $this->_where = $value;
        $this->invalidateResults();
        $this->_sql = null;
        return $this;
    }

    public function getWhere() {
        return $this->_where;
    }

    public function setGroups($groups) {
        $this->invalidateResults();
        $this->_groups = $groups;
        $this->_sql = null;
        return $this;
    }

    public function getGroups() {
        return $this->_groups;
    }

    public function setOrders($orders) {
        $this->invalidateResults();
        $this->_orders = $orders;
        $this->_sql = null;
        return $this;
    }

    public function getOrders() {
        return $this->_orders;
    }

    public function setLimit($value) {
        if($this->_limit !== $value) $this->invalidateResults();
        $this->_limit = $value;
        $this->_sql = null;
        return $this;
    }

    public function getLimit() {
        return $this->_limit;
    }

    public function setOffset($value) {
        if($this->_offset !== $value) $this->invalidateResults();
        $this->_offset = $value;
        $this->_sql = null;
        return $this;
    }

    public function getOffset() {
        return $this->_offset;
    }

    public function setValues($values) {
        $this->invalidateResults();
        $this->_values = $values;
        $this->_sql = null;
        return $this;
    }

    public function getValues() {
        return $this->_values;
    }

    /**
     * Sets or updates the user-parameters of a Query.
     *
     * @param array $values
     * @return static
     */
    public function setParams($values=[]) {
        if($values===null) $values = [];
        $this->invalidateResults();
        // Normalize indices: Numeric-indexed user parameters must be 0-based
        $keys = array_keys($values);
        $i=0;
        foreach($keys as &$index) {
            if(is_int($index)) $index = $i++;
        }
        $values = array_combine($keys, array_values($values));

        $this->_params = $values;
        foreach($this->_params as $name=>$value) {
            $this->_innerParams[$name] = $values;
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getParams() {
        if(!$this->_sql) $this->_sql = $this->build();
        return $this->_innerParams;
    }

    /**
     * @throws Exception
     */
    public function getSql() {
        if(!$this->_sql) $this->_sql = $this->build();
        return $this->_sql;
    }

    /**
     * Set the query to return arrays. Chainable.
     *
     * @return $this
     */
    public function asArray() {
        if($this->modelClass) $this->invalidateResults();
        $this->modelClass = null;
        return $this;
    }

    /**
     * Set the query to return Models
     *
     * @param string|Model $modelClass
     * @return $this
     * @throws Exception -- if modelClass is not a Model
     */
    public function asModel($modelClass) {
        if(!is_a($modelClass, Model::class, true)) throw new Exception('modelClass must be a Model');
        if($this->modelClass !== $modelClass) $this->invalidateResults();
        $this->modelClass = $modelClass;
        if(!$this->from) $this->from = [$modelClass];
        return $this;
    }

    /**
     * Returns all records as an array of Models or arrays depending on result model set.
     *
     * @return Model[]|array[]|null -- array of Model instances or null on failure
     * @throws Exception
     */
    public function getAll() {
        try {
            if($this->sql && $this->_all!==null) return $this->_all;
            $start = microtime(true);
            $this->stmt = $this->prepareStatement();
            $this->stmt->execute();
            $result = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            if(ENV_DEV) App::log(LogLevel::DEBUG, sprintf('Elapsed=%.3f SQL = %s', microtime(true) - $start, $this->sql));
        }
        catch(Exception $e) {
            App::log(LogLevel::ERROR, $e->getMessage(). ' while executing SQL: '.$this->sql);
            if(ENV_DEV) throw new Exception($e->getMessage(). ' while executing SQL: '.$this->sql, 500, $e);
            else throw $e;
        }

        // Populate model
        if($this->modelClass) {
            foreach ($result as &$value) {
                $value = new $this->modelClass($oldValues = $value);
                /** @var Model $value */
                $value->setOldAttributes($oldValues);
                $value->afterLoad();
            }
        }

        if($this->_indexField) {
            $result = array_combine(array_map(function($row) {
                return ArrayHelper::getValue($row, $this->_indexField);
            }, $result), $result);
        }
        return $this->_all = $result;
    }

    /**
     * Returns the first record from a Query result
     *
     * If you want to query only the first and no more later, use limit(1) explicitly.
     * Subsequent getNext() calls can be used if no limit(1) was restricted.
     *
     * @return null|array|Model
     * @throws Exception -- when `prepare`, `bind`, `execute` or `fetch` failed
     */
    public function getOne() {
        if($this->sql && $this->_one!==null) return $this->_one;
        $start = microtime(true);
        if(!($this->stmt = $this->prepareStatement([PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]))) throw new Exception($this->lastError(true));
        if(!$this->stmt->execute()) throw new Exception($this->lastError(true));
        $result = $this->stmt->fetch(PDO::FETCH_ASSOC);
        $error = $this->lastError(true);
        if($result === false && substr($error,0,5) != '00000') throw new Exception($error);
        if(!$result) return $this->_one = null; // Note: Null value is not stored effectively...
        if(ENV_DEV) App::log(LogLevel::DEBUG, sprintf('Elapsed=%.3f SQL = %s', microtime(true) - $start, $this->sql));

        // Populate model
        if($this->modelClass) {
            /** @var Model $model */
            $model = new $this->modelClass($result);
            $model->setOldAttributes($result);
            $model->afterLoad();
            $model->lastQuery = $this;
            $result = $model;
        }

        // Return as array
        return $this->_one = $result;
    }

    /**
     * Returns the next record from an already executed Query result
     * Can be used after getOne(), otherwise it returns null
     *
     * @return null|array|Model
     * @throws Exception
     */
    public function getNext() {
        if(!$this->stmt) return null;
        $result = $this->stmt->fetch(PDO::FETCH_ASSOC);

        // Populate model
        if($this->modelClass) {
            if ($result === null) $model = null;
            else {
                $model = new $this->modelClass($result);
                $model->setOldAttributes($result);
            }
            return $model;
        }

        // Return as array
        return $result;
    }

    /**
     * Returns an array of the values from the given column of all records
     *
     * @param int|string $column -- column may be indexed by both integers and column names
     * @return array
     */
    public function getColumn($column=0) {
        if($column===0 && $this->sql && $this->_column!==null) return $this->_column;
        $this->stmt = $this->prepareStatement([PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
        $this->stmt->execute();
        $result = [];
        $index = $this->_indexField ?: false;
        while ($row = $this->stmt->fetch()) {
            if($index) {
                if(!isset($row[$index])) throw new Exception("Invalid index column $index");
                if(!$row[$index]) {
                    throw new Exception("Empty index: ".json_encode(['column'=>$column, 'row'=>$row, 'index'=>$index]));
                }
                $result[$row[$index]] = $row[$column];
            }
            else $result[] = $row[$column];
        }

        if($column===0) $this->_column = $result;
        return $result;
    }

    /**
     * Returns the value of the first (or given) column of the first record
     *
     * @param int|string $column -- column may be indexed by both integers and column names
     * @return mixed
     * @throws Exception
     */
    public function getScalar($column=0) {
        if($column===0 && $this->sql && $this->_scalar!==null) return $this->_scalar;
        $this->stmt = null;
        try {
            $this->stmt = $this->prepareStatement();
            if(!$this->stmt->execute()) {
                throw new Exception('Error executing SQL: ' . implode('; ', $this->stmt->errorInfo()));
            }
            // Empty result is not error
            if(($row = $this->stmt->fetch()) === false) {
                $errorInfo = $this->stmt->errorInfo();
                if($errorInfo[0]!='00000')
                    throw new Exception('Error fetching SQL: ' . implode('; ', $errorInfo));
                $row = null;
            }
        }
        catch(Throwable $e) {
            $phase = $this->stmt ? 'executing' : 'preparing';
            $details = ENV_DEV ? '; ' . $phase.' "'.$this->sql.'" with parameters ['.implode(',', array_keys($this->params)).']' : '';
            throw new Exception('PDO Error '.$e->getMessage().$details);
        }
        $result = $row===null ? null : $row[$column];
        if($column===0) $this->_scalar = $result;
        return $result;
    }

    /**
     * Returns the number of the rows in the dataset of the query
     *
     * @return int
     */
    public function getCount() {
        if($this->sql && $this->_count!==null) return $this->_count;
        $query = clone $this;
        $query->select('(count(*))')->orderBy(null);
        return $this->_count = $query->scalar;
    }

    /**
     * Converts the Query to an option array (for cloning purposes)
     * @return array
     */
    public function toArray() {
        return [
            'connection' => $this->_connection,
            'modelClass' => $this->_modelClass,
            'type' => $this->_type,
            'fields' => $this->_fields,
            'from' => $this->_from,
            'joins' => $this->_joins,
            'where' => $this->_where,
            'groupBy' => $this->_groups,
            'orders' => $this->_orders,
            'offset' => $this->_offset,
            'limit' => $this->_limit,
            'values' => $this->_values,
            'indexField' => $this->_indexField,
            'params' => $this->_params,
            'sql' => $this->_sql,
        ];
    }

    /**
     * Execute a non-select query
     *
     * @return bool -- success
     * @throws Exception
     */
    public function execute() {
        if($this->type=='SELECT') throw new Exception('Use get*() methods for SELECT queries');
        $this->stmt = $this->prepareStatement();
        try {
            return $this->stmt->execute();
        }
        catch(Exception $e) {
            throw new Exception('Error executing '.$this->sql. ' with params ('.implode(', ', $this->params). ')', 0, $e);
        }
    }

    /**
     * Returns number of rows affected by executing the last SQL statement
     *
     * @return int
     */
    public function getAffected() {
        return $this->stmt->rowCount();
    }

    /**
     * Returns the actual db connection object
     *
     * @return Connection
     */
    public function getConnection() {
        if(!$this->_connection) $this->_connection = App::$app->getConnection(true);
        return $this->_connection;
    }

    /**
     * Returns the actual db connection object
     *
     * @param Connection $connection
     * @return static
     */
    public function setConnection($connection) {
        if($this->_connection !== $connection) $this->invalidateResults();
        $this->_connection = $connection;
        return $this;
    }

    /**
     * Set the connection for the Query. Chainable.
     *
     * @param Connection $connection
     * @return $this
     */
    public function connect($connection) {
        return $this->setConnection($connection);
    }

    /**
     * Sets the model class and/or field list of the INSERT query.
     * (Sets the type to INSERT). Chainable.
     *
     * Usage cases:
     *
     * ->into(modelClass)
     * ->into(modelClass, fields)
     * ->into(fields) -- can be used only if modelClass already specified
     *
     * @param string $modelClass -- The model to insert into (or fields)
     * @param array $fieldList -- Array of literal field-names or other expressions
     * @return $this
     * @throws Exception -- if modelClass is not valid
     */
    public function into($modelClass, $fieldList=null) {
        $this->type = 'INSERT';
        if($this->modelClass && is_array($modelClass) && $fieldList===null) {
            $this->_fields = $modelClass;
        }
        else {
            if(is_array($modelClass)) throw new Exception('fieldList can be specified alone only if modelClass is already stored');
            if(!is_a($modelClass, Model::class, true)) throw new Exception('Invalid modelClass');
            $this->modelClass = $modelClass;
            if ($fieldList) $this->_fields = $fieldList;
        }
        $this->_sql = null;
        return $this;
    }

    /**
     * Creates an UPDATE Query object
     *
     * @param string|Model|array $modelClass -- name of the main Model/table or options array
     * @param array $fields -- field-names to select, default is all fields of the model.
     * @param array $condition
     * @param array $params
     * @param Connection|null $connection
     *
     * Alternative call method: first parameter is an associative options array with the above options
     *
     * @return Query -- the query object
     * @throws Exception
     */
    public static function createSelect($modelClass=null, $fields=null, $condition=null, $params=null, $connection=null) {
        if(is_array($modelClass) &&  func_num_args()==1) {
            $options = $modelClass;
            $query = new Query($options);
            $query->type = 'SELECT';
        }
        else {
            if($connection && ! $connection instanceof Connection) throw new Exception('Invalid connection parameter');
            $query = new Query([
                'type'=>'SELECT',
                'modelClass' => $modelClass,
                'fields' => $fields,
                'where' => $condition,
                'params' => $params,
                'connection' => $connection,
            ]);
        }
        return $query;
    }

    /**
     * Creates an INSERT Query object
     *
     * @param string|Model|array $modelName -- model name or options array for all the other parameters
     * @param array $fields --
     *   - if null, model attributes are used (or values' keys if associative)
     *   - if $values is null, $field must contain fieldName=>value pairs. For expression values use associative $values
     * @param array $values -- array of row value arrays
     *   - if $fields includes values, must be NULL, otherwise NULL is not allowed.
     *   - if values is a numeric-indexed 2-dim array, the literal values will be inserted into multiple rows. No expressions are allowed.
     *   - if values is an associative array, it considered as fieldName=>expression. $fields must be null. Use for single row only.
     *   - if values is a Query, a sub-query will be used.
     * @param Connection|null $connection -- default is app's connection
     *
     * Alternative call method: first parameter is an associative options array with the above options
     *
     * Alternative build:
     *
     * createInsert(modelName)->into(fieldNames)->values(values-or-query)
     *
     * @return Query -- the query object
     * @throws Exception
     */
    public static function createInsert($modelName, $fields=null, $values=null, $connection=null) {
        if(is_array($modelName) &&  func_num_args()==1) {
            $options = $modelName;
            $query = new Query(array_merge(array('type'=>'insert'), $options));
        }
        else {
            if($connection && ! $connection instanceof Connection) throw new Exception('Invalid connection parameter');
            $query = new Query(array('type'=>'insert',
                'modelClass' => $modelName,
                'fields' => $fields,
                'values' => $values,
                'connection' => $connection,
            ));
        }
        return $query;
    }


    /**
     * Creates an UPDATE Query object
     *
     * @param string|array $modelClass -- name of the main Model/table or options array
     * @param array $values -- fieldName=>expression pairs (for explicit literal values, use $db->literal() on values or use parametrized ?-s)
     * @param array $condition
     * @param array $params
     * @param Connection|null $connection
     *
     * Alternative call method: first parameter is an associative options array with the above options
     *
     * @return Query -- the query object
     * @throws Exception
     */
    public static function createUpdate($modelClass, $values=null, $condition=null, $params=null, $connection=null) {
        if(is_array($modelClass) &&  func_num_args()==1) {
            $options = $modelClass;
            $query = new Query($options);
            $query->type = 'UPDATE';
        }
        else {
            if($connection && ! $connection instanceof Connection) throw new Exception('Invalid connection parameter');
            $query = new Query([
                'type'=>'UPDATE',
                'modelClass' => $modelClass,
                'values' => $values,
                'where' => $condition,
                'params' => $params,
                'connection' => $connection,
            ]);
        }
        return $query;
    }

    /**
     * Creates a Query object and builds a DELETE  sql
     *
     * @param string|array $modelClass -- main table model class or options array
     * @param array $condition -- expression
     * @param array $params
     * @param Connection|null $connection
     *
     * Alternative call method: first parameter is an associative options array with Query options
     *
     * @return Query -- the query object
     * @throws Exception
     */
    public static function createDelete($modelClass, $condition=null, $params=null, $connection=null) {
        if(is_array($modelClass) &&  func_num_args()==1) {
            $options = $modelClass;
        }
        else {
            $options = array(
                'modelClass' => $modelClass,
                'where' => $condition,
                'params' => $params,
                'connection' => $connection,
            );
        }
        if(isset($options['connection']) && ! $options['connection'] instanceof Connection) throw new Exception('Invalid connection parameter');
        return new Query(array_merge(['type'=>'DELETE'], $options));
    }

    /**
     * Build an SQL query and prepare with bindings
     *
     * @param array $options -- PDO prepare options
     * @return PDOStatement|false -- false if bind or prepare failed
     */
    public function prepareStatement($options=[]) {
        $stmt = $this->connection->pdo->prepare($this->sql, $options); // this actually builds up the SQL statement and computes the inner parameters
        if($stmt===false) return false;
        if($this->_innerParams) foreach($this->_innerParams as $name=>&$value) {
            if(!$stmt->bindParam(is_int($name) ? $name+1 : $name, $value)) return false;
        }
        return $stmt;
    }

    /**
     * Build an SQL query and collect implicit parameters
     *
     * @throws Exception
     */
    public function build() {
        $this->resetParams();
        if (!is_callable($builder = [$this, 'build' . ucfirst(strtolower($this->type))])) {
            throw new Exception('Invalid query type `' . $this->type . '`');
        }
        return $this->_sql = $builder();
    }

    /**
     * Called before building to reset inner-params to the original user params.
     * For testing purposes this feature must be public.
     * Don't call it directly.
     *
     * @return void
     */
    public function resetParams() {
        $this->_innerParams = $this->_params;
    }

    /**
     * Invalidates all computed result properties of the Query.
     * Called when the Query has been changed its SQL string or binding
     *
     * @return void
     */
    public function invalidateResults() {
        $this->_count = null;
        $this->_one = null;
        $this->_all = null;
        $this->_scalar = null;
        $this->_column = null;
    }

    /**
     * Returns table-name for a model name.
     * Returns itself if model name is not a Model classname, assuming a literal tableName
     *
     * @param string $modelName -- model classname
     * @return mixed
     * @throws Exception -- if the class has no tableName() method or throws an exception
     */
    public function modelTableName($modelName) {
        if(class_exists($modelName) && is_a($modelName, Model::class, true)) {
            if (!is_callable($callable = array($modelName, 'tableName'))) {
                throw new Exception("Invalid model class: $modelName");
            }
            return call_user_func($callable);
        }
        return $modelName;
    }

    /**
     * Return field names from the named model without alias
     *
     * @param $modelName
     *
     * @return array (numeric indexed)
     * @throws Exception
     */
    public function modelFields($modelName) {
        if(!is_callable(array($modelName, 'databaseAttributes'))) throw new Exception("Invalid model classname '$modelName'");
        return call_user_func(array($modelName, 'databaseAttributes'), $this);
    }

    /**
     * If query has joins or more than one from table, adds alias keys to all from table to force using alias
     * in subsequent field-name generation. The auto-generated alias is based on the short Class name.
     *
     * Also returns alias name of main model table if exists, or null.
     *
     * @param array|null $aliases -- (output only) returns the generated alis names
     * @return string -- alias name of main model table or null
     * @throws Exception
     */
    public function normalizeAlias(&$aliases=null) {
        // Collect existing aliases
        $aliases = [];
        if(is_array($this->_from)) foreach($this->_from as $i=>$model) if(!is_int($i)) $aliases[] = $i;
        if(is_array($this->_joins)) foreach($this->_joins as $i=>$def) if(!is_int($i)) $aliases[] = $i;

        // Generate aliases for FROM tables
        if(!empty($this->joins) || is_array($this->_from) && count($this->_from)>1) {
            if(is_string($this->_from)) {
                $aliases[] = $alias = ($this->_from)::tableName();
                $this->_from = array($alias => $this->_from);
            }
            else if(is_array($this->_from)) {
                /**
                 * @var int $i
                 * @var string|Model $model
                 */
                foreach($this->_from as $i=>$model) {
                    if(is_int($i)) {
                        $aliases[] = $alias = $this->findUniqueAlias($model::tableName(), $aliases);
                        $this->_from[$alias] = $model;
                        unset($this->_from[$i]);
                    }
                }
            }
        }

        // Generate aliases for JOIN tables
        if(is_array($this->_joins)) {
            foreach ($this->_joins as $i => $joinDef) {
                if(is_int($i)) {
                    $aliases[] = $alias = $this->findUniqueAlias($joinDef[0]::tableName(), $aliases);
                    $this->_joins[$alias] = $joinDef;
                    unset($this->_joins[$i]);
                }
            }
        }

        // Return the alias of the main model
        $alias = is_array($this->from) ? array_search($this->modelClass, $this->from) : null;
        if($alias===false || is_int($alias)) $alias = null;
        return $alias;
    }

    public function findUniqueAlias($baseName, $aliases) {
        $alias = $baseName;
        $i=1;
        while(in_array($alias, $aliases)) {
            $alias = $baseName.($i++);
        }
        return $alias;
    }

    /**
     * Builds a SELECT query
     *
     * @throws Exception
     */
    public function buildSelect() {
        $alias = $this->normalizeAlias();
        $from = $this->from;
        if(!$from && $this->modelClass) $from = $this->modelClass;
        if(!$from && $this->_where) throw new Exception('FROM or modelClass is mandatory when WHERE part is present');
        return 'SELECT '.
            trim($this->buildFieldNames($from, $this->fields, $number)) .
            $this->buildFrom($from) .
            $this->buildJoins($this->modelClass, $alias, $this->joins) .
            $this->buildWhere($alias) .
            $this->buildGroupBy($this->_groups, $alias) .
            // TODO: implement HAVING -- later
            // TODO: implement UNION, INTERSECT, EXCEPT -- later
            $this->buildOrders($this->orders, $alias) . // Auto alias not applied in order to use alias fields.
            ($this->limit ? ' LIMIT '.(int)$this->limit : '') .
            ($this->offset ? ' OFFSET '.(int)$this->offset : '');
    }

    /**
     * Builds the FROM part
     *
     * @param Model[] $from
     * @return string
     * @throws Exception
     */
    public function buildFrom($from) {
        return $from ? (' FROM '. $this->buildTableNames($from)) : '';
    }

    /**
     * Returns a comma-separated aliased list of safe table names by rules of the connection database.
     * Processes .-notations.
     *
     * @param array|string $models -- array of model names or alias=>model elements or single model name
     *
     * @return string
     * @throws Exception -- if any of the models is not a valid Model or sub-Query
     */
    public function buildTableNames($models) {
        if(!is_array($models)) return $this->buildTableName($models);
        return implode(', ', array_map(function($model, $alias) {
            if(is_integer($alias)) $alias='';
            return $this->buildTableName($model, $alias);
        }, $models, array_keys($models)));
    }

    /**
     * Returns a safe single table name by rules of the connection database.
     * Processes .-notations.
     * Also accepts a sub-query, in this case the sub-query itself will be built enclosed in parentheses.
     *
     * @param string|Query $model -- main table model name or a sub-query
     * @param string $alias -- optional alias for table name
     *
     * @return string
     * @throws Exception -- if the name contains the delimiter or the $model classname is not a valid Model
     */
    public function buildTableName($model, $alias='') {
        if($model instanceof Query) {
            $model->connection = $this->connection;
            return '(' . $model->sql . ')'. ($alias ? (' '.static::quoteFieldName($alias)) : '');
        }
        /** @var string $model */
        Assertions::assertString($model);

        $name = $this->modelTableName($model);
        return static::quoteFieldName($name). (($alias && ($alias!=$name)) ? (' '.static::quoteFieldName($alias)) : '');
    }

    /**
     * Builds an SQL fragment used in SELECT, DELETE and UPDATE as WHERE part.
     * If condition expression is empty, the WHERE clause will not present.
     * Expression may be:
     *
     *  - array of (field=>value)
     *  - other expression array, see {@see Query::buildExpression()}
     *  - a single scalar literal for single pk
     *
     * @param null $alias
     *
     * @return string
     * @throws Exception
     * @see Query::buildExpression
     */
    public function buildWhere($alias=null) {
        $condition = $this->_where;
        if(is_null($condition) || is_array($condition) && count($condition)==0) return '';
        if($condition===true) return '';
        if($condition===false) return ' WHERE false';
        if(!is_array($condition)) {
            $pk = call_user_func(array($this->modelClass, 'primaryKey')); // returns array of field names
            if(count($pk)>1) throw new Exception('Scalar condition can be used only for single primary key');
            if(is_array($pk)) $pk = $pk[0];
            $condition = array($pk => $this->literal($condition));
        }
        return ' WHERE '.$this->buildExpression($condition, $alias);
    }

    /**
     * @param array $groupBy
     * @param string $alias
     *
     * @return string
     * @throws Exception -- if the expression in the definition is invalid
     */
    public function buildGroupBy($groupBy, $alias=null) {
        if(is_null($groupBy) || $groupBy==='' || $groupBy===array()) return '';
        if(is_string($groupBy)) $groupBy = array($groupBy);
        if(!is_array($groupBy)) throw new Exception('GROUP BY mut be an array');
        $db = $this;
        return ' GROUP BY ' . implode(', ', array_map(function($f) use($alias, $db) {
                return $db->buildExpression($f, $alias);
            }, $groupBy));
    }

    /**
     * Replaces all associative entries to expression format in an expression list
     * (Converts fieldList to expressionList, field values treated as expressions)
     * E.g. array('apple', 'p'=>'peach') --> array('apple', array('=', 'p', 'peach'))
     *
     * @param array $list
     *
     * @return array
     */
    public function preprocessAssociative($list) {
        foreach($list as $index=>$value) {
            if(!is_integer($index)) {
                unset($list[$index]);
                $list[] = array('=', $index, $value);
            }
        }
        return $list;
    }

    /**
     * Builds an SQL fragment used as expression for example in WHERE part or ON part in a JOIN.
     * The expression must not be empty.
     * Expression may be:
     *
     *  - string beginning with '(' will be returned literally. No identifier quoting or model check will be applied.
     *  - string beginning with single quote or E' will be treated as string literal. Closing quote will be omitted and internal double single quotes are allowed
     *  - integer or float: a numeric literal
     *  - '?' is a numeric-indexed parameters. Don't use, always use ':' named parameters.
     *  - string beginning with a ':' is string-indexed parameter
     *  - any other string: a field name. Unqualified field name will be qualified with alias if alias is given.
     *  - array(OP, ...): operator with operands (expressions), only numeric indices {@see $_operators}
     *  - array(FN(), ...): function call (function existence not checked) (arguments as expressions)
     *  - array(fieldName=>value, ...) ==> array('AND', array('=', fieldName, value), ...)
     *  - any other array: same as ['AND', ...]. Note: empty array will be TRUE (implicit AND-ed zero elements)
     *
     * All subexpressions will be evaluated recursively.
     *
     * @param mixed $expression
     * @param string $alias -- optional alias for model
     * @param integer $precedence -- whether it's necessary to use parenthesis with operator below this level.: 0=always, null=never
     *
     * @return string
     * @throws Exception
     * @throws Exception on invalid expression, unknown operator, invalid number of operands, extra whitespaces in field names
     */
    public function buildExpression($expression, $alias=null, $precedence=null) {
        if(is_null($expression)) return 'NULL';
        if(is_null($precedence)) $precedence=999;
        if(is_bool($expression)) {
            return $expression ? 'TRUE' : 'FALSE';
        }
        if(is_integer($expression) || is_float($expression)) {
            return $expression;
        }
        if(is_string($expression)) {
            if(is_numeric($expression)) return "'$expression'";
            if($expression=='') return "''";
            $expression = trim($expression);
            $c = substr($expression,0,1);
            if($c=='(') {
                if(substr($expression,-1)==')') return $expression;
                throw new Exception('Invalid expression: missing `)`');
            }
            if($c=="'" || $c=="E" && substr($expression,1,1)=="'") {
                if(substr($expression,-1)=="'") return $expression;
                throw new Exception('Invalid expression: missing `\'`');
            }

            // Parameter placeholders
            if($expression=='?') return $expression;
            if(preg_match('~^:[\w]+$~', $expression)) return $expression;

            $output = null;

            // Field names: (field, field alias, "field", table.field, "table"."field", "table"."field" "alias")
            if(strpos($expression, ' ')) {
                $ee = preg_split('/\s+/', $expression);
                if(strtoupper($ee[1])=='AS' && count($ee)==3)  { $output = $ee[2]; $expression=$ee[0]; }
                else if(count($ee)==2)  { $output = $ee[1]; $expression=$ee[0]; }
                else throw new Exception(sprintf('Invalid expression: too many whitespaces in field name `%s` ', $expression));
            }
            return $this->buildFieldName($expression, $alias, $output);
        }
        if(is_array($expression)) {
            if(isset($expression[0])) {
                if(is_array($expression[0])) $op = 'AND';
                else $op = array_shift($expression);
                if(substr($op,0,1)=='[' || substr($op,0,1)=='\'') throw new Exception('Invalid operator: '.$op);
                if(substr($op,0,1)=='(') {
                    if(substr($op,-1)==')') return $op;
                    throw new Exception('Invalid expression: missing `)`');
                }
                $expression = $this->preprocessAssociative($expression);
                if(($ops = static::isOperator($op))!==false) {
                    $pr = static::operatorPrecedence($op);
                    $opr = $this->connection->operatorName(strtoupper($op));
                    if(!$opr) throw new Exception("Operator `$op` is not supported");

                    if($ops<4 && count($expression)!=$ops) throw new Exception("Operator `$opr` requires $ops operand");
                    switch($ops) {
                        case 0:
                            return $opr;
                        case 1:
                            return $opr . ' '. $this->buildExpression($expression[0], $alias, $pr);
                        case 2:
                        case 4:
                            // now $expression is an operand list.
                            if($opr=='||' && empty($expression)) return "''";
                            if($opr=='*' && empty($expression)) return 1;
                            if($opr=='+' && empty($expression)) return 0;
                            if($opr=='&' && empty($expression)) return -1;
                            if($opr=='|' && empty($expression)) return 0;
                            try {
                                $db = $this;
                                $subExpressions = @array_map(function($x) use($alias, $pr, $db) {
                                    return $db->buildExpression($x, $alias, $pr);
                                }, $expression);
                                if($opr=='AND') $subExpressions = array_filter($subExpressions, function($se) { return $se != 'TRUE'; });
                                if($opr=='AND' && empty($subExpressions)) return 'TRUE';
                                if($opr=='AND' && in_array('FALSE', $subExpressions)) return 'FALSE';
                                if($opr=='OR') $subExpressions = array_filter($subExpressions, function($se) { return $se != 'FALSE'; });
                                if($opr=='OR' && empty($subExpressions)) return 'FALSE';
                                if($opr=='OR' && in_array('TRUE', $subExpressions)) return 'TRUE';
                                $r = implode(' '.$opr.' ', $subExpressions);
                                break;
                            }
                            catch(Exception $e) {
                                throw new Exception("Invalid internal expression at operator `$opr`: ".json_encode($expression), 0, $e);
                            }
                        case 3:
                            $r = $this->buildExpression($expression[0], $alias, $pr) .
                                ' '. $opr . ' '. $this->buildExpression($expression[1], $alias, $pr) .
                                ' AND ' . $this->buildExpression($expression[2], $alias, $pr);
                            break;
                        case 5:
                            if(count($expression)!=1) throw new Exception("Operator `$opr` requires exactly one operand");
                            return $this->buildExpression($expression[0], $alias, $pr).' '.$opr;
                        case 6:
                            if(count($expression)!=2) throw new Exception("Operator `$opr` requires exactly two operands.");
                            if($opr=='IN' && empty($expression[1])) return 'FALSE';
                            $r = $this->buildExpression($expression[0], $alias, $pr) .
                                ' '. $opr . ' ('. $this->buildExpressionList($expression[1], $alias) .
                                ')';
                            break;
                        case 7:
                            return $this->buildOperator($opr, $expression, $alias);
                        default:
                            throw new Exception("Invalid operator definition ($opr=$ops)", 0);
                    }

                    // Determine () on precedence order
                    if(count($expression)>1 && $pr>$precedence) $r='('.$r.')';
                    return $r;
                }
                // Function call
                if(substr($op, -2)=='()') {
                    $db = $this;
                    return substr($op,0,-1).implode(', ', array_map(function($x) use($alias, $db) {
                            return $db->buildExpression($x, $alias);
                        }, $expression)).')';
                }
                else {
                    throw new Exception("Unknown operator `$op`: ".json_encode($expression), 0);
                }
            }
            if(empty($expression)) return 'TRUE';
            // fieldName=>expression condition list (always literal values, no subexpressions)
            if(ArrayHelper::isAssociative($expression)) {
                $op = 'AND';
                $ex = array_merge([$op], array_map(function($value, $index){
                    if($value===null || $value===[]) return ['is null', $index];
                    if(is_array($value)) {
                        $value = array_map(function($item) {
                            $paramName = ArrayHelper::genUniqueIndex($this->_innerParams, '_p');
                            $this->_innerParams[$paramName] = static::literal($item);
                            return ':'.$paramName;
                        }, $value);
                        return ['in', $index, $value];
                    }
                    if($value instanceof Query) {
                        return ['in', $index, $value];
                    }
                    $paramName = ArrayHelper::genUniqueIndex($this->_innerParams, '_p');
                    $this->_innerParams[$paramName] = static::literal($value);
                    return ['=', $index, ':'.$paramName];
                }, array_values($expression), array_keys($expression)));
                return $this->buildExpression($ex, $alias, $precedence);
            }
        }
        if($expression instanceof Query) {
            $expression->connection = $this->connection;
            $expression->params = $this->_innerParams;
            $result = '('.$expression->sql.')';
            $this->_innerParams = $expression->params;
            return $result;
        }
        if(is_object($expression)) return $this->connection->quoteValue($this->literal($expression));
        throw new Exception('Invalid expression '.json_encode($expression));
    }

    /**
     * Returns the number of operands of the operator if $op is an operator, or false if not.
     *
     * @param $op
     *
     * @return integer|false
     */
    public static function isOperator($op) {
        if(!is_string($op)) return false;
        return ArrayHelper::getValue(static::$_operators, strtolower($op), false);
    }

    /**
     * Returns precedence of the operator if $op is an operator, or false if not.
     *
     * @param $op
     *
     * @return integer|false
     */
    public static function operatorPrecedence($op) {
        if(!is_string($op)) return false;
        return array_search(strtolower($op), array_keys(static::$_operators));
    }

    /**
     * Returns vendor-dependent expression part
     *
     * Define or override opBuild() methods in vendor drivers for each op.
     *
     * @param string $op -- the operator
     * @param array $expression -- list of operands
     * @param null $alias
     *
     * @return string -- the expression built
     * @throws Exception
     */
    public function buildOperator($op, $expression, $alias=null) {
        $functionName = strtolower($op).'Build';
        if(!is_callable(array($this, $functionName))) throw new Exception("Expression build function `$functionName` is missing");
        return call_user_func(array($this, $functionName), $expression, $alias);
    }

    /**
     * Builds an SQL fragment used in SELECT as JOIN part.
     * Scalar string will be returned literally, without any identifier quoting.
     *
     * Conditions are the remained parts of a $join array.
     * Condition may be a `mainField=>foreignField` associative pair, or any numeric-indexed other expression
     *
     * @param string|array $model -- main table's model-name (single table only)
     * @param string $mainAlias -- main table's alias
     * @param array $joins -- list of joined models as [alias=>[model, join-type, condition, ...], ...]
     *
     * @return string
     * @throws Exception -- if any of the join types is invalid, or the condition is missing or invalid
     * @see Self::buildJoin() for join item details
     */
    public function buildJoins($model, $mainAlias, $joins) {
        if(empty($joins)) return '';
        if(is_string($joins)) return $joins;
        $result = '';

        foreach($joins as $key=>$join) {
            if(is_array($join)) {
                /** @var string|Model $foreignModel */
                $foreignModel = array_shift($join);

                $alias = is_integer($key) ? null : $key;
                $type = '';
                if(isset($join[0]) && is_string($join[0]) && in_array(
                        $joinType = strtoupper($join[0]),
                        array('LEFT', 'RIGHT', 'INNER', 'LEFT OUTER', 'RIGHT OUTER', 'FULL', 'FULL OUTER'))
                ) {
                    $type = $joinType;
                    array_shift($join);
                }
                $conditions = $join;
            } else throw new Exception('Invalid join data');
            $result .= ($result ? ' ' : '') . $this->buildJoin($model, $mainAlias, $foreignModel, $alias, $type, $conditions);
        }
        return ($result ? ' ' : '') . $result;
    }

    /**
     * Builds a single JOIN part as SQL fragment.
     *
     * @param string|Model $model -- main model to join to (has no alias)
     * @param string $mainAlias -- optional alias for main model
     * @param array|Model $foreignModel -- name of foreign model
     * @param string $alias -- alias of joined table - mandatory
     * @param string $type -- 'left' or 'inner' or empty, etc.
     * @param array $conditions -- 'ON' conditions [mainField=>foreignField, ...] or other expressions (mandatory for some JOIN types, must be empty for others.)
     * you may use any other expression using array($expression)
     *
     * @return string
     * @throws Exception -- if join type is invalid, or the condition is missing or invalid
     * @see Self::buildJoins()
     */
    public function buildJoin($model, $mainAlias, $foreignModel, $alias, $type, $conditions) {
        if(!is_a($foreignModel, Model::class, true)) throw new Exception("Model class `$foreignModel` is missing");
        $foreignTableName = $foreignModel::tableName();

        // type: CROSS | [NATURAL] { [INNER] | { LEFT | RIGHT | FULL } [OUTER] }
        $type = strtoupper(trim($type));
        $types = explode(' ', $type);
        $hasOn = count($types)==0 || ($types[0]!='CROSS' && $types[0]!='NATURAL');
        $valid = preg_match('/^(CROSS|((NATURAL)?\s?(INNER|((LEFT|RIGHT|FULL)( OUTER)?))?))$/', $type);
        if(!$valid) throw new Exception("Invalid join type $type");

        if($hasOn) {
            $foreignAlias = $alias ?: $foreignTableName;
            if(empty($conditions) || !is_array($conditions)) throw new Exception("Missing condition for $type JOIN");
            // Processing conditions: array('creator'=>'id'), --> array('AND', 'creator'=>'alias.id')  --> 'model.creator = alias.id'

            if(ArrayHelper::isAssociative($conditions)) {
                $expression = array('AND');
                foreach($conditions as $mainField=>$foreignField) {
                    Assertions::assertString($foreignField);
                    $field = strpos($foreignField, '.')===false ? $foreignAlias.'.'.$foreignField : $foreignField;
                    $expression[] = array('=', $mainField, $field);
                }
            }
            else {
                $expression = $conditions;
            }

            if(!$mainAlias) $mainAlias = $model::tableName();
            return sprintf('%s JOIN %s ON %s',
                $type,
                $this->quoteSingleName($foreignTableName) . ($alias && $alias != $foreignTableName ? ' '.$this->quoteSingleName($alias) : ''),
                $this->buildExpression($expression, $mainAlias)
            );
        }
        else {
            if(!empty($conditions)) throw new Exception("Needless condition for $type JOIN");
            return sprintf('%s JOIN %s',
                $type,
                $this->quoteSingleName($foreignTableName) . ($alias ? ' '.$this->quoteSingleName($alias) : '')
            );
        }
    }

    /**
     * Builds a ','-separated expression list (without surrounding parenthesis)
     * or a sub-query
     *
     * @param array|Query $list -- array of expressions or a sub-query
     * @param integer $alias
     *
     * @return string
     * @throws Exception
     */
    public function buildExpressionList($list, $alias=null) {
        if($list instanceof Query) {
            $list->connection = $this->connection;
            $list->params = $this->_innerParams;
            $result = $list->sql;
            $this->_innerParams = $list->params;
            return $result;
        }
        if(!is_array($list)) {
            throw new Exception('Invalid expression list', $list);
        }
        $db = $this;
        return implode(', ', array_map(function($expression) use($alias, $db) {
            return $db->buildExpression($expression, $alias);
        }, $list));
    }

    /**
     * Builds a ','-separated list of table names
     *
     * @throws Exception
     */
    public function buildTables($tables) {
        if(is_string($tables)) return static::quoteFieldName($tables);
        return implode(', ', (array)$this->from);
    }

    /**
     * Builds an SQL fragment used as ORDER BY part
     * Returns empty string on empty orders.
     *
     * @param array $orders  -- array of order definitions as in {@see buildOrderList()}
     * @param string $alias -- optional alias for model, used for field names in {@see buildFieldNameValue()}
     *
     * @return string -- an 'ORDER BY ...' clause or empty string
     * @throws Exception  -- if $orders parameter or any of the orders is invalid
     */
    public function buildOrders($orders, $alias=null) {
        if(is_null($orders)) return '';
        if(is_string($orders)) $orders = array($orders);
        if(!is_array($orders)) throw new Exception('Orders mut be an array');
        if(!count($orders)) return '';
        return ' ORDER BY '.$this->buildOrderList($orders, $alias);
    }

    /**
     * Builds an order list without 'ORDER BY' keyword.
     *
     * @param array $orders -- numeric indexed array of strings or array(fieldName, order direction, nulls)
     *  - simple string beginning with '(' will be used literally,
     *  - all other simple string is a field name
     *  - order direction: ORDER_ASC (default), ORDER_DESC
     *  - nulls: NULLS_FIRST (default), NULLS_LAST
     *  - array expressions in second form are evaluated
     * @param string $alias -- optional alias for model, used for field names in {@see buildFieldNameValue()}
     *
     * @return string -- the SQL fragment to be placed after the 'ORDER BY' or empty if no elements in orders.
     * @throws Exception -- if $orders parameter or any of the orders is invalid
     */
    public function buildOrderList($orders, $alias=null) {
        if(is_null($orders)) return '';
        if(is_string($orders)) $orders = array($orders);
        if(!is_array($orders)) throw new Exception('Orders mut be an array');
        if(!count($orders)) return '';
        // NULLs works directly on postgresql only, mysql must generate slave item ISNULL() if LAST is requested
        $db = $this;
        try {
            return implode(', ', array_map(function($o) use ($alias, $db) {
                return $db->buildOrder($o, $alias);
            }, $orders));
        }
        catch(Exception $e) {
            throw new Exception('Invalid order list', $e->getCode(), $e);
        }
    }

    /**
     * Builds an order item for ORDER BY part
     *
     * @param string|array $o -- (literal) or fieldName or array(fieldName, order direction, nulls)
     *  - simple string beginning with '(' will be used literally,
     *  - all other simple string is a field name
     *  - order direction: ORDER_ASC (default), ORDER_DESC
     *  - nulls: NULLS_FIRST (default), NULLS_LAST
     * @param string $alias -- optional alias for model, used for field names
     *
     * @return string -- an item for ORDER BY, without separator comma
     * @throws Exception -- if order is empty, a closing parenthesis is missing, or failed to build (invalid)
     */
    public function buildOrder($o, $alias=null) {
        if(empty($o)) throw new Exception('Empty order item.');
        if(is_string($o)) {
            if(substr($o, 0, 1)=='(') {
                if(substr($o, -1)!=')') throw new Exception("Missing closing parenthesis in order item '$o'");
                return substr($o,1,-1);
            }
            if(strpos($o, ' ')!==false) {
                $words = explode(' ', trim($o));
                $words[0] = $this->buildFieldName($words[0], $alias);
                return implode(' ', $words);
            }
            return $this->buildFieldName($o, $alias);
        }
        if(is_array($o)) {
            if(!isset($o[0])) throw new Exception('Invalid order definition');
            $fieldName = $o[0];
            $order = ArrayHelper::getValue($o, 1, self::ORDER_ASC);
            $nulls = ArrayHelper::getValue($o, 2, self::NULLS_FIRST);
            $preorder = $nulls==self::NULLS_LAST && !$this->connection->supportsOrderNullsLast() ?
                ('ISNULL('.$this->buildFieldName($fieldName, $alias).'), ') :
                '';
            try {
                return $preorder . $this->buildFieldName($fieldName, $alias) .
                    ($order == self::ORDER_DESC || strtoupper($order) == 'DESC' ? ' DESC' : '') .
                    (($this->connection->supportsOrderNullsLast() && ($nulls == self::NULLS_LAST || strtoupper($order) == 'NULLS LAST')) ? ' NULLS LAST' : '');
            }
            catch(Exception $e) {
                throw new Exception('Invalid order expression', $fieldName, $e);
            }
        }
        throw new Exception('Invalid order item.');
    }

    /**
     * Builds an UPDATE query based on query data
     *
     * UPDATE modelName SET fields/values WHERE condition
     * Currently does not support `UPDATE ... FROM` construction.
     *
     * @return string
     * @throws Exception
     * @throws Exception
     */
    public function buildUpdate() {
        $alias = $this->normalizeAlias();
        $tablePart = $this->buildTableName($this->modelClass);      // MySQL supports multiple table name here, not implemented (later)
        $updatePart = $this->buildUpdateSet($this->_values);
        $fromPart = $this->from ? $this->buildFrom($this->from) : ''; // MySQL does not support this syntax
        $wherePart = $this->buildWhere($alias);
        return 'UPDATE ' . $tablePart . ' SET ' . $updatePart . $fromPart . $wherePart;
    }

    /**
     * Builds an SQL fragment for 'set' part of an update
     *
     * @param array $values -- fieldName=>expression pairs
     * @return string -- without 'SET'
     * @throws Exception
     */
    public function buildUpdateSet($values) {
        $db = $this;
        return implode(', ', array_map(function($fieldName, $exp) use($db) {
            return
                $db->buildFieldName($fieldName) .
                ' = ' .
                $db->buildExpression($exp);
        }, array_keys($values), array_values($values)));
    }

    /**
     * Builds a DELETE query based on query data
     *
     * DELETE FROM modelName WHERE condition
     *
     * @return string
     * @throws Exception
     */
    public function buildDelete() {
        return /** @lang */'DELETE FROM ' .
            $this->buildTableName($this->modelClass) .
            $this->buildWhere();
    }

    /**
     * Builds an INSERT query
     *
     * INSERT INTO modelName (_fields) _values
     *
     * Rules for fields, values, expressions and literals:
     *
     *   - if **\_fields** is null, and **\_values** is a numeric-indexed array, model attributes are used;
     *   - if **\_fields** is null, and **\_values** is an associative array of fieldName=>expression pairs, a single-row INSERT will be built;
     *   - if **\_fields** is a numeric-indexed array of field names, **\_values** contains a numeric-indexed 2-dim array of literal values of multiple rows.
     *   - if **\_fields** is an associative array containing fieldName=>value pairs, **\_values** must be null. No expressions are allowed. Parametrized query will be built.
     *   - if **\_values** is a Query, a sub-query will be built for the VALUES part. Field names are in **\_fields** or default attributes are used.
     *   - if **\_values** is a string, it is used as an SQL fragment for VALUES part. Use with care! Field names are in **\_fields** or default attributes are used.
     *
     * @return string
     * @throws Exception
     */
    public function buildInsert() {
        $fields = $this->_fields;
        $values = $this->_values;

        if(!$fields && is_array($values) && ArrayHelper::isAssociative($values)) {
            // Single row INSERT using expressions
            $fieldsPart = $this->buildFieldNames($this->modelClass, array_keys($values), $number);
            $valuesPart = 'VALUES ('.$this->buildExpressionList(array_values($values)).')';
        }
        elseif(!$values) {
            // Single row INSERT using literal values -- generates an explicit parametrized query
            if(!$fields) throw new Exception('Must specify either `fields` or `values` for INSERT');
            if(!is_array($fields) || !ArrayHelper::isAssociative($fields)) throw new Exception('`values` must contain the values or `fields` must be an associative array');
            $fieldsPart = $this->buildFieldNames($this->modelClass, array_keys($fields), $number);
            $values = array_map(function($value) {
                $paramName = ArrayHelper::genUniqueIndex($this->_innerParams, '_p');
                $this->_innerParams[$paramName] = $value;
                return ':'.$paramName;
            }, array_values($fields));
            $valuesPart = 'VALUES ('.implode(', ', $values).')';
        }
        else {
            // Multi-row INSERT using literal values or sub-Query. Not a parametrized query.
            if(!($values instanceof Query) && ArrayHelper::isAssociative($values)) throw new Exception('`values` must be a 2-dim numeric-indexed array of literal values of rows or a sub-Query');
            $fieldsPart = $this->buildFieldNames($this->modelClass, $fields, $number);
            $valuesPart = $this->buildValues($values, $number);
        }

        $tablePart = $this->buildTableName($this->modelClass);

        return 'INSERT INTO ' . $tablePart . ' (' . $fieldsPart. ') '. $valuesPart;
    }

    /**
     * Builds the VALUES part of an INSERT statement. No expressions are allowed.
     *
     * If too many values are supplied: ignores the extra values.
     * If insufficient values are supplied: NULL values are inserted
     *
     * @param array $values -- array of row value arrays
     *   if values is an array, the literal values of multiple rows will be inserted.
     *   if values is a Query, a sub-query will be used.
     *   if values is a string, user literally (as an SQL-fragment) --- use it with care!
     * @param integer $number -- the number of fields to be inserted, default unspecified.
     * If given, insufficient number of values will be filled out with NULLS. Not used if values is a string or sub-Query.
     * Mandatory in multi-row inserts (number of fields from fields part or number of generated default attributes)
     *
     * @return string -- the values part (including VALUES keyword if necessary)
     * @throws Exception -- if a row is not an array, has too many values, or $values is an invalid type.
     */
    public function buildValues($values, $number=null) {
        if(is_string($values)) return $values;
        if(is_array($values)) {
            $result = 'VALUES ';
            foreach($values as $index=>$valueList) {
                $result .= '(';
                if($valueList===null) throw new Exception("#$index: Null values row.");
                if(!is_array($valueList)) throw new Exception("#$index: Invalid values row. Values must contain arrays.", $valueList);
                $n0 = count($valueList);
                $n = $number ?: $n0; // The actual number of values to be inserted
                if($n0 > $n) throw new Exception("Too many values in the list #$index. $n0>$n", array($number, $valueList));
                for($i=0;$i<$n;$i++) {
                    $result .= ($i>=$n0) ? 'NULL' : $this->connection->quoteValue($valueList[$i]);
                    if($i<$n-1) $result .= ', ';
                }
                $result .= ')';
                if($index<count($values)-1) $result .= ",\n";
            }
            return $result;
        }
        if($values instanceof Query) {
            $values->connection = $this->connection;
            return $values->sql;
        }
        $type = gettype($values);
        throw new Exception("Invalid datatype ($type) for values part.", $values);
    }

    /**
     * Builds an SQL fragment of field names used in SELECT or INSERT or GROUP BY part.
     *
     * @param string|array $models -- main table name or array(alias=>tableName)
     *        If an alias is given, it will be applied as prefix of the corresponding fields
     *        if no alias, single table's fields will not use prefix, otherwise table names will be used as prefix
     * @param array|null $fields -- array of field names (or single field name as string). If null, model attributes are used
     *        with or without table prefixes,
     *        or alias => fieldName
     *        or alias => (expression)
     * @param $number -- returns the number of fields to be inserted
     *
     * @return string
     * @throws Exception
     */
    public function buildFieldNames($models, $fields, &$number) {
        $forceAlias = false;
        if($models===null) $models=[]; // throw new Exception('No model or table name given for select');
        if(!is_array($models)) $models = array($models);
        //if(count($models)<1) throw new Exception('Must specify at least one model');
        if(count($models)>1) $forceAlias = true;
        if(ArrayHelper::isAssociative($models, false)) $forceAlias = true;

        $mk = array_keys($models);
        /** @var string $alias -- alias of first (main) table, will be used as prefix for all unqualified field when more than one table is present. */
        $alias = $mk ? $mk[0] : null;
        if(is_integer($alias)) {
            $alias = $this->modelTableName($models[$alias]);
        }

        // If no fields are specified, all fields of all tables (or sub-queries) are generated with aliases
        if(is_null($fields)) {
            $fields = $this->allModelFields($models);
        } else {
            if(!is_array($fields)) $fields = array($fields);
            if($forceAlias) {
                $fields = array_map(function($f) use($alias) {
                    if(!is_string($f)) return $f;
                    if(substr($f,0,1)=="'") return $f;
                    if(substr($f,0,1)=='(') return $f;
                    if(strpos($f, '.')) return $f;
                    return $alias.'.'.$f;
                }, $fields);
            }
        }

        if(!is_array($fields)) $fields = array($fields);
        $number = count($fields);
        $prefix = $forceAlias ? $alias : null;
        $db = $this;
        $aliases = array_keys($fields);
        return implode(', ', array_map(function($f, $a, $i) use($prefix, $fields, $aliases, $db) {
            $output = is_integer($a) ? '' : $a;
            // Auto output alias-prefixed field-name if similar field-name exist before it's index
            if(!$output && is_scalar($f) && strpos($f, '.')) {
                $field_name = AppHelper::substring_after($f, '.', true);
                // Search for the position of the occurrence of the field_name, where no alias present
                $pos = ArrayHelper::array_find_key($fields, function($item, $alias) use($f, $field_name) {
                    if(!is_numeric($alias)) return false;
                    return $item!==$f && $field_name == AppHelper::substring_after($item, '.', true);
                });
                if(is_numeric($pos) && $pos < $i) $output =  str_replace('.', '_', $f);
            }
            try {
                return $db->buildFieldName($f, $prefix, $output);
            }
            catch(Exception $e) {
                throw new Exception("Invalid field name definition '$f'", 0, $e);
            }
        }, $fields, $aliases, array_keys($aliases)));
    }

    /**
     * Returns a safe field name by rules of the connection database with optional alias
     * Processes .-notations
     * Does not check the model
     *
     * Possible names: fieldName, tableAlias.fieldName, "fieldName", "tableAlias"."fieldName"
     *
     * @param string|array|Query $name -- quoted or unquoted field name with an optional table (-alias) prefix; or other expression or a subQuery with single field
     * @param string $prefix -- optional table alias connected with '.'. If fieldName already has a prefix, alias will be ignored.
     * @param string $output -- optional output alias connected with 'AS'
     *
     * @return string
     * @throws Exception
     */
    public function buildFieldName($name, $prefix=null, $output=null) {
        return $this->buildFieldNameValue($name, $prefix) . ($output ? ' AS '.$this->quoteSingleName($output) : '');
    }

    /**
     * Returns a safe field name by rules of the connection database without alias
     *
     * @param string|array $name field name quoted or unquoted with optional table part; or other expression
     * @param string $prefix -- optional table alias connected with '.'. If field name already has a prefix, alias will be ignored.
     *
     * @return string
     * @throws Exception
     */
    public function buildFieldNameValue($name, $prefix=null) {
        if(is_array($name)) {
            return $this->buildExpression($name, $prefix);
        }
        if($name instanceof Query) {
            $field = '('.$name->buildSelect().')';
            $this->_params = array_merge($this->_params, $name->params);
            return $field;
        }
        if(is_object($name)) throw new Exception('invalid field definition');
        if(is_int($name)) return $name;
        if($name=='?' || preg_match('/^:\w+$/', $name)) return $name;
        if(substr($name,0,1)=="'" || substr($name,-1)=="'") return $name;
        if(substr($name,0,1)=='(' || substr($name,-1)==')') return $name;
        if(strpos($name, '.')===false && $prefix) $name = $prefix.'.'.$name;
        return $this->quoteFieldName($name);
    }

    /**
     * Quotes table- or fieldName (vendor-specific)
     * If name is already quoted, returns the name.
     *
     * @param string $name
     *
     * @return string the quoted name
     * @throws Exception -- if the name contains the delimiter
     */
    public function quoteSingleName($name) {
        $name = trim($name);
        if($name=='*') return $name;
        $delimiter = $this->connection->quoteIdentifier('_'); // delimiter[0] and delimiter[2] is used.
        $delimited = strlen($name)>2 && substr($name, 0, 1)==$delimiter[0] && substr($name, -1)==$delimiter[2];
        $bareName = $delimited ? substr($name, 1, -1) : $name;
        if(strpos($bareName, $delimiter[0]) !== false || strpos($bareName, $delimiter[2]) !== false) {
            throw new Exception("Invalid field name ($name)");
        }
        if($delimited) return $name;
        return $this->connection->quoteIdentifier($name);
    }

    /**
     * Returns last error occurred in last executed statement, if any.
     * The error message is a ';'-separated PDO errorInfo structure.
     * The ANSI-code '00000' in the first tag indicates success.
     *
     * @param bool $pdo -- read error from connected PDO, ignore last stmt
     * @return string|null -- "ANSI-code; driver-code; driver-message" or null if no statement available.
     */
    public function lastError($pdo=false) {
        if($pdo) return implode('; ', $this->connection->pdo->errorInfo());
        return $this->stmt ? implode('; ', $this->stmt->errorInfo()) : null;
    }

    /**
     * Adds a LIKE-type filtering rule to the query, if value is not empty
     *
     * @param string|null $value
     * @param string $field
     * @return static
     */
    public function filterLike($value, string $field) {
        if($value!='') {
            $patternName = AppHelper::toNameID($field, '_', '');
            $patternExpression = ['concat()', "'%'", ['lower()', ':' . $patternName], "'%'"];
            $this->andWhere(['LIKE', ['lower()', $field], $patternExpression], [$patternName => $value]);
        }
        return $this;
    }

    /**
     * Adds an equal-type filtering rule to the query, if value is not empty
     *
     * @param string|null $value
     * @param string $field
     * @return Query
     */
    public function filterIs($value, string $field) {
        if($value!=='' && $value!==null) {
            $this->andWhere([$field => $value]);
        }
        return $this;
    }

    /**
     * Adds an "In-the-referred-table" type filtering rule to the query, if value is not empty
     * The searched foreign field can only be an SQL field. To use virtual field, see {@see filterInReferredModel}
     *
     * @param string|null $value -- the value to find in the "name" field of the referred Model
     * @param string $field -- the field referring to the foreign model
     * @param string|Model $foreignClass -- the referred Model
     * @param string $foreignValueField -- the "name" field to search in the foreign table
     * @param string|null $foreignIdField -- default is primary key: works only with a single primary key
     * @return Query
     * @throws Exception
     */
    public function filterInReferred($value, string $field, $foreignClass, $foreignValueField, $foreignIdField=null) {
        if($value!='') {
            if(!$foreignIdField) $foreignIdField = $foreignClass::primaryKey();
            $patternName = AppHelper::toNameID($field, '_', '');
            $patternExpression = ['concat()', "'%'", ['lower()', ':' . $patternName], "'%'"];
            $candidatesQuery = $foreignClass::createQuery()
                ->where(['LIKE', ['lower()', $foreignValueField], $patternExpression], [$patternName => $this->literal($value)])
                ->select($foreignIdField);
            $this->andWhere(['IN', $field, array_map([$this->connection, 'quoteValue'], $candidatesQuery->column)]);
        }
        return $this;
    }

    /**
     * Adds an "In-the-referred-table" type filtering rule to the query, if value is not empty.
     * Works with virtual fields, but loads the entire referred table, so use only with small datasets.
     * The comparison method is "value is substring of the value of $foreignValueField" in case-insensitive manner and UTF8-safe.
     * The searched foreign field can be a virtual field, unlike in {@see filterInReferred}
     *
     * @param string|null $value -- the value to find in the "name" ($foreignValueField) field of the referred Model
     * @param string $field -- the field referring to the foreign model
     * @param string|Model $foreignClass -- the referred Model
     * @param string $foreignValueField -- the "name" field to search in the foreign model (can also be a virtual field)
     * @param string|null $foreignIdField -- default is primary key: works only with a single primary key
     * @return void
     * @throws Exception
     */
    public function filterInReferredModels($value, string $field, string $foreignClass, string $foreignValueField, ?string $foreignIdField=null) {
        if($value!='') {
            if(!$foreignIdField) $foreignIdField = $foreignClass::primaryKey()[0];
            $referenceValues = [];
            foreach($foreignClass::getAll() as $referredModel) {
                if(mb_strpos(mb_strtolower($referredModel->$foreignValueField), mb_strtolower($value))!==false) {
                    $referenceValues[] = $referredModel->$foreignIdField;
                }
            }
            $this->andWhere(['IN', $field, array_map([$this->connection, 'quoteValue'], $referenceValues)]);
        }
    }
}
