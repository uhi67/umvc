<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use DateTime;
use Exception;
use JsonSerializable;
use PDO;
use ReflectionException;
use Throwable;

/**
 * Database Model class based on PDO
 *
 * Create your derived Model classes for each database tables from this
 *
 * You should override:
 * - tableName() -- default is short lowercase classname
 * - primaryKey() -- default is ['id']
 * - autoIncrement() -- default is first field of primaryKey()
 * - attributeLabels() -- default is the field names converted to uppercase words
 *
 * @property boolean $isNew -- true if the record is new and not saved yet.
 * @property array $oldAttributes -- the original values of the record from the database
 * @property Connection $connection;
 * @package UMVC Simple Application Framework
 */
class Model extends BaseModel implements JsonSerializable {
    /** @var Query -- the last executed Query */
    public $lastQuery;

    /** @var array $attribute values indexed by attribute names */
    protected $_attributes = array();

    /** @var array $_metadata -- Cached metadata of tables indexed by tableName */
    private static $_metadata = [];

    /** @var Connection $_connection -- The explicit set database connection. If not set, App:::$app->connection is used  */
    private $_connection;

    /**
     * @var array|null old attribute values (populated from the database) indexed by attribute names.
     * This is `null` if the record is new.
     */
    private $_oldAttributes;

    /**
     * Must return the name of the table.
     * The default implementation returns the un-camelized classname
     *
     * @return string
     */
    public static function tableName() {
        return AppHelper::underscore(static::shortName());
    }

    /**
     * Must return an array of primary key fields
     *
     * Default implementation returns ['id']
     *
     * @return array of field-names
     */
    public static function primaryKey() {
        return ['id'];
    }

    /**
     * Must return the autoincrement fields.
     *
     * Default is first of primaryKey(). You must override it with an empty array if no autoincrement field in your model.
     *
     * Currently, only one field-name is supported.
     *
     * @return array
     */
    public static function autoIncrement() {
        $pk = static::primaryKey();
        if(count($pk)>1) return [];
        return array_slice($pk, 0, 1);
    }

    /**
     * Returns the list of all attribute names of the model.
     *
     * The default implementation will return all column names of the table associated with this Model class.
     *
     * @return array list of attribute names.
     * @throws Exception
     */
    public static function attributes() {
        return static::databaseAttributes();
    }

    /**
     * Returns the list of database-related attribute names of the model
     *
     * The default implementation will return all column names of the table associated with this Model class.
     * Skips field names beginning with underscore (_).
     * Field names are sorted.
     *
     * @return array list of attribute names alphabetically sorted
     * @throws Exception
     */
    public static function databaseAttributes() {
        $tableName = static::tableName();
        if ($tableName == 'model') throw new Exception('Use derived models instead of Model', $tableName);

        $metadata = static::tableMetaData();
        if (!$metadata) throw new Exception("Metadata of table $tableName not found");

        $fields = array_filter(array_keys($metadata), function ($name) {
            return substr($name, 0, 1) != '_';
        });
        sort($fields);

        return $fields;
    }

    /**
     * Returns table metadata as
     *
     * ```php
     * [
     *     fieldName => [
     *        'num' => <Field number starting at 1>
     *        'type' => <data type, e.g. varchar, int4>
     *        'len' => <internal storage size of field. -1 or null for varying>
     *        'not null' => <boolean>
     *        'has default' => <boolean>
     *     ],
     *     ...
     * ]
     * ```
     *
     * Memory-cached (short-term) and app-cached (long-term, 1h)
     *
     * @return array|null if not found
     * @throws Exception
     */
    public static function tableMetaData($connection=null) {
        if(!$connection) $connection = App::$app->getConnection(true);
        $tableName = static::tableName();
        $metadata = ArrayHelper::getValue(self::$_metadata, $tableName);

        if(!$metadata) {
            $metadata = App::$app->cached('_model_metadata_' . static::shortName(), function () use ($tableName, $connection) {
                return $connection->tableMetadata($tableName);
            }, 3600);
            self::$_metadata[$tableName] = $metadata;
        }
        return $metadata;
    }

    /**
     * Returns one record of the model
     *
     * Condition may be:
     * - scalar: a single value of the primary key
     * - associative array: [fieldName=>value, ...]
     * - expression array: [operator, expression, ...] example: ['AND', ['OR', ['name'=>''], ['name'=>null]], ['=', 'name', 'login']]
     *
     * @param array|null $condition -- returns null if record with condition not found
     * @return static
     * @see Query::buildExpression()
     * @throws Exception -- when the PDO operation failed
     */
    public static function getOne($condition=null, $connection=null) {
        if(!$connection) $connection = App::$app->getConnection(true);
        // Note: FROM is implicit from called class
        return static::createQuery()->connect($connection)->where($condition)->limit(1)->one;
    }

    /**
     * Returns all records as an array of Models
     *
     * @param array $condition -- fieldName=>value pairs or other expression
     * @param array|null $orders
     * @param Connection|null $connection
     * @return array|null -- array of Model instances or null on failure (not indexed by primary key)
     * @throws Exception|ReflectionException
     */
    public static function getAll($condition=null, $orders=null, $connection=null) {
        if(!$connection) $connection = App::$app->getConnection(true);
        // Note: FROM is implicit from called class
        return static::createQuery(['orders'=>$orders])->connect($connection)->where($condition)->getAll();
    }

    /**
     * Returns a value indicating whether the current record is new.
     * @return bool whether the record is new and should be inserted when calling save().
     */
    public function getIsNew() {
        return $this->_oldAttributes === null;
    }

    /**
     * Sets the value indicating whether the record is new.
     * @param bool $value whether the record is new and should be inserted when calling save().
     * @see getIsNew()
     */
    public function setIsNew($value) {
        $this->_oldAttributes = $value ? null : $this->_attributes;
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * Only the changed attribute values will be inserted into database.
     * If the table's autoincrement key is `null` during insertion, it will be populated with the actual value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param array $attributes list of attributes that need to be saved. Defaults to `null` = all fields
     *
     * @return bool the record is inserted successfully.
     * @throws Exception in case insert failed.
     */
    public function insert($attributes = null) {
        /** the changed fieldName=>value pairs */
        $values = $this->getDirtyAttributes($attributes);

        /** autoincrement primary key field names */
        $aa = static::autoIncrement();
        // If $attributes is null, exclude autoincrement field(s) containing null values
        if(is_null($attributes)) {
            foreach($aa as $ai) {
                if(array_key_exists($ai, $values) && $values[$ai]===null) {
                    unset($values[$ai]);
                }
            }
        }

        $query = null;
        try {
            // We use the `fields` property of the Query, because have literal values in the $values array.
            $query = new Query(['type'=>'INSERT', 'modelClass'=>get_called_class(), 'fields'=>$values, 'connection'=>$this->connection]);
            $this->lastQuery = $query;
            $success = $query->execute();
            if(!$success) return false;
        }
        catch(Exception $e) {
            try { $sql = $query ? $query->sql : ''; } catch(Throwable $e) { $sql='Error at building SQL: '.$e->getMessage(); }
            $error = $query ? $query->connection->lastError : $e->getMessage();
            throw new Exception('Insert failed: '.$e->getMessage(). ' Database error: '. $error .'. Query was: '.$sql, 0, $e);
        }

        // Populate autoincrement fields
        if(!empty($aa)) {
            $auto = $aa[0]; // Only one autoincrement field is supported
            if(!array_key_exists($auto, $values)) {
                $id = $this->connection->pdo->lastInsertId();
                $this->setAttribute($auto, $id);
                $values[$auto] = $id;
            }
        }
        $this->setOldAttributes($values);
        return true;
    }

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * Note that it is possible the update does not affect any row in the table.
     * In this case, this method will return 0. For this reason, you should use the following
     * code to check if update() is successful or not:
     *
     * ```php
     * if ($customer->update() !== false) {
     *     // update successful
     * } else {
     *     // update failed
     * }
     * ```
     *
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved. If empty array, no operation will be performed, and returns 0.
     * Only changed (dirty) attributes will be included, if there isn't any, no operation will be performed, and returns 0.
     *
     * @return bool success
     * @throws Exception in case update failed.
     * @throws ReflectionException
     */
    public function update($attributeNames = null) {
        if($attributeNames===array()) return 0;

        $values = $this->getDirtyAttributes($attributeNames);
        if($values===array()) return 0;
        $condition = $this->getOldPrimaryKey(true);
        $success = static::updateAll($values, $condition, $this->connection, $this->lastQuery);

        if($success) {
            foreach ($values as $name => $value) {
                $this->_oldAttributes[$name] = $value;
            }
        }
        return $success;
    }

    /**
     * Updates all matching record in database. Literal values only
     *
     * @param array $values -- associative list of fieldName=>value pairs (no expressions are allowed here)
     * @param array|integer $condition -- condition for where part the sql
     *  - [fieldName=>value, ...]
     *  - literal value of single primary key
     *  - other expression formats
     * @param PDO|null $connection
     * @param null $query -- (output only) return the query created. Affected rows are accessible as $query->affected
     * @return bool -- success
     * @throws Exception
     */
    public static function updateAll($values, $condition, $connection=null, &$query=null) {
        if(!$connection) $connection = App::$app->getConnection(true);

        // Convert `fieldName=>value` pairs to parametrized `fieldName=>':param'` expressions
        $params = [];
        $values = array_map(function($value) use(&$params) {
            $paramName = ArrayHelper::genUniqueIndex($params, '_m');
            $params[$paramName] = $value;
            return ':'.$paramName;
        }, $values);

        $query = Query::createUpdate(get_called_class(), $values, $condition, $params, $connection);
        if(!$query->execute()) return false;
        return true;
    }

    /**
     * Deletes a Model record without considering transaction.
     * The last values of attributes will be still available in the model after deletion.
     *
     * @return bool -- success
     * @throws Exception
     */
    public function delete() {
        $condition = $this->getOldPrimaryKey(true);
        $result = static::deleteAll($condition, null, null, $this->lastQuery);
        $this->setOldAttributes(null);
        return $result;
    }

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * Customer::deleteAll('status' => 3);
     * ```
     *
     * > Warning: If you do not specify any condition, this method will not delete all rows, but throws an exception.
     *
     * For example an equivalent of the example above would be:
     *
     * ```php
     * $models = Customer::all('(status = 3)');
     * foreach($models as $model) {
     *     $model->delete();
     * }
     * ```
     *
     * @param array|string|int|null $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     *  - [fieldName=>value, ...]
     *  - literal value of single primary key
     *  - other expression formats
     * @param array $params the parameter values for $1 parameters or name=>value pairs for $name parameters
     * @param Connection|null $connection -- database connection. Default is from App.
     * @param null $query -- (output only) return the query created. Affected rows are accessible as $query->affected
     * @return bool success
     * @throws Exception
     */
    public static function deleteAll($condition = null, $params = array(), $connection=null, &$query=null) {
        if(!$connection) $connection = App::$app->getConnection(true);
        $query = Query::createDelete(get_called_class(), $condition, $params, $connection);
        if(!$query->execute()) return false;
        return true;
    }

    /**
     * Saves the current record without validation
     *
     * This method will call {@see insert()} when {@see isNew} is `true`, or {@see update()}
     * when {@see isNew} is `false`.
     *
     * For example, to save a customer record:
     *
     * ```php
     * $customer = new Customer; // or $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->save();
     * ```
     *
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     *
     * @return bool whether the saving succeeded
     * @throws Exception
     * @throws ReflectionException
     */
    public function save($attributeNames = null) {
        if ($this->isNew) {
            return $this->insert($attributeNames);
        } else {
            return $this->update($attributeNames);
        }
    }

    /**
     * Returns the old primary key value(s).
     * This refers to the primary key value that is populated into the record from the database.
     * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
     *
     * @param bool $asArray whether to return the primary key value as an array. If `true`,
     * the return value will be an array with column name as key and column value as value.
     * If this is `false` (default), a scalar value will be returned for non-composite primary key.
     *
     * @return mixed the old primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is `true`. A string is returned otherwise (null will be returned if
     * the key value is null or if there's no primary key of the model).
     */
    public function getOldPrimaryKey($asArray = false) {
        $keys = $this->primaryKey();
        if (empty($keys)) {
            return $asArray ? [] : null;
        }
        if (!$asArray && count($keys) === 1) {
            return $this->_oldAttributes[$keys[0]] ?? null;
        } else {
            $values = array();
            foreach ($keys as $name) {
                $values[$name] = $this->_oldAttributes[$name] ?? null;
            }
            return $values;
        }
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     * The comparison of new and old values is made for identical values using `===`.
     * If you want all fields to be returned, use setOldAttributes(null) before.
     *
     * The returned values are initialized each as per Model::databaseValue().
     * Only database attributes count.
     *
     * @param string[]|null $names the names of the attributes whose values may be returned, null = all
     *
     * @return array the changed name-value pairs

     * @throws Exception
     */
    public function getDirtyAttributes($names = null) {
        $databaseAttributes = static::databaseAttributes();
        Assertions::assertArray($databaseAttributes);
        if ($names === null) {
            $names = $databaseAttributes;
        } else {
            Assertions::assertArray($names);
            $names = array_intersect($names, $databaseAttributes);
        }
        $names = array_flip($names);
        $attributes = array();
        if ($this->_oldAttributes === null) {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name])) {
                    $attributes[$name] = static::databaseValue($value);
                }
            }
        } else {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name]) && (!array_key_exists($name, $this->_oldAttributes) || $value !== $this->_oldAttributes[$name])) {
                    $attributes[$name] = static::databaseValue($value);
                }
            }
        }
        return $attributes;
    }

    /**
     * Sets the old attribute values.
     * All existing old attribute values will be discarded.
     * @param array|null $values old attribute values to be set.
     * If set to `null` this record is considered to be new.
     */
    public function setOldAttributes($values) {
        $this->_oldAttributes = $values;
    }

    /**
     * Gets the old attribute values.
     * The old values are retrieved from database, and not overwritten yet by actual values with an update()
     * If the record is new, null value will be returned
     */
    public function getOldAttributes() {
        return $this->_oldAttributes;
    }

    /**
     * Returns the old (database-saved) value of the attribute.
     *
     * @param string $name the attribute name
     * @return mixed the old attribute value. `null` if the attribute is not loaded before or does not exist.
     * @see hasAttribute()
     */
    public function getOldAttribute($name) {
        return $this->_oldAttributes[$name] ?? null;
    }

    /**
     * Sets the old value of the named attribute.
     *
     * @param string $name the attribute name
     * @param mixed $value the old attribute value.
     *

     * @throws Exception if the named attribute does not exist.
     * @see hasAttribute()
     */
    public function setOldAttribute($name, $value) {
        if (isset($this->_oldAttributes[$name]) || $this->hasAttribute($name)) {
            $this->_oldAttributes[$name] = $value;
        } else {
            throw new Exception('Model `'.$this->shortName.'` does not have attribute named "' . $name . '".');
        }
    }

    /**
     * Returns the value of a property
     * Attributes and related objects can be accessed like properties.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $component->property;`.
     *
     * @param string $name the property name
     *
     * @return mixed the property value

     * @throws Exception if the property is not defined
     * @throws ReflectionException
     */
    public function __get($name) {
        // Retrieved attribute
        if (isset($this->_attributes[$name]) || array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        }

        // Existing but not loaded attribute
        if ($this->hasAttribute($name)) {
            $getter = 'get' . $name; // function names are case-insensitive in PHP!
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }
            else return null;
        }

        // Reference composition (e.g. 'ref1.name')
        if(strpos($name, '.')) {
            return ArrayHelper::getValue($this, $name);
        }

        // Other
        return parent::__get($name);
    }

    /**
     * Sets the value of a Model attribute via PHP setter magic method.
     *
     * @param string $name property name
     * @param mixed $value property value
     *
     * @throws Exception
     */
    public function __set($name, $value) {
        if (static::hasAttribute($name)) {
            $this->_attributes[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }


    /**
     * ## Validates model to uniqueness of the given field
     *
     * Returns true if the given fields has a value of null.
     *
     * @param string $fieldName -- for single field (null if called as multiple)
     *
     * @return bool -- model is valid
     * @throws Exception  -- when a DB request failed
     */
    public function validateUnique($fieldName) {
        $value = $this->$fieldName;
        if(is_null($value)) return true;
        $e = static::getOne([$fieldName=>$value]);
        if(!$this->isNew) {
            // If already saved, itself must be ignored
            if (!$e) return true;
            $pk = $this->getOldPrimaryKey(true);
            foreach ($pk as $k => $v) if ($e->$k == $v) return true;
        }
        if($e) {
            $this->addError($fieldName, 'must be unique');
            return false;
        }
        return true;
    }

    /**
     * Checks if the value exists in the table of the referenced Model
     * Null values are accepted (use 'mandatory' validation if not)
     *
     * @param string $fieldName
     * @param string|Model $referencedModel
     *
     * @return bool
     * @throws Exception
     */
    public function validateReferences($fieldName, $referencedModel) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if($value===null) return true;
        if(!is_a($referencedModel, Model::class, true)) throw new Exception('Parameter 2 must be a className of a Model');

        $exist = $referencedModel::getOne($value);
        if(!$exist) return $this->addError($fieldName, 'refers to a non-existing record');
        return true;
    }

    /**
     * Convert the form order definitions to Query builder orderBy definitions.
     * Only the given fields are enabled (not enabled fields are skipped).
     *
     * enabledOrders may contain:
     * - simple fieldName
     * - user_fieldName => model_fieldName
     * - user_fieldName => definition-array with 2 elements for ASC and DESC rule
     * - example: 'modified'=> ['deleted_at asc, modified_at asc, created_at asc', 'deleted_at desc, modified_at desc, created_at desc']
     *
     * @param array $orders -- [fieldName=>'DIR;PRIORITY', notSorted=>null, ...] as it got from the HTML form
     * @param array $orderDefinitions -- enabled and custom order clauses by fields, directions separated by ;
     * @return array -- [[fieldName, order-direction, nulls], ...] -- value prepared for {@see Query::orderBy()}
     * @throws Exception -- if $customOrders is given but not valid
     */
    public static function orderDef($orders, $orderDefinitions=null) {
        if($orderDefinitions===null) $orderDefinitions = array_keys(Model::rules());

        foreach($orderDefinitions as $key=> $value) { if(is_int($key)) { $orderDefinitions[$value] = $value; unset($orderDefinitions[$key]); }}

        $orderBy = [];
        // Sortable array with items [field, direction (int), priority]
        $tempOrders = array_filter(array_map(function($o, $fieldName) use($orderDefinitions) {
            if(!($p = strpos($o, ';'))) return null;
            $priority = (int)substr($o,$p+1);
            $direction = strtoupper(substr($o,0,$p));
            $dirValue = array_search($direction, [Query::ORDER_ASC=>'ASC', Query::ORDER_DESC=>'DESC']);
            if($dirValue===false) return null; // A sort-rule with invalid direction is skipped
            return ['field'=>$fieldName, 'direction'=>$dirValue, 'priority'=>$priority];
        }, $orders, array_keys($orders)));
        usort($tempOrders, function($a,$b) { return $a['priority'] - $b['priority']; });

        foreach($tempOrders as $ord) {
            $field = $ord['field'];
            if(array_key_exists($field, $orderDefinitions)) {
                if($def = $orderDefinitions[$field]) {
                    if(is_array($def)) {
                        $cls = $orderDefinitions[$field];
                        if (count($cls) != 2) {
                            throw new Exception("Custom order clause for field '$field' must have exactly two expression-list separated by ';', got '$orderDefinitions[$field]'");
                        }
                        // Depending on user intention, select the first or second part of the custom order definition, and parse it
                        $orderExpressions = explode(',', $cls[strtoupper($ord['direction']) == 1 ? 1 : 0]);
                        foreach ($orderExpressions as $orderExpression) {
                            $p = strrpos($orderExpression, ' ');
                            if ($p) {
                                $direction = strtoupper(substr($orderExpression, $p + 1));
                                $dirValue = array_search($direction, [Query::ORDER_ASC => 'ASC', Query::ORDER_DESC => 'DESC']);
                            }
                            if (!$p || $dirValue === false) {
                                throw new Exception("Custom order clause for field '$field' must contain order expressions with a direction keyword separated by ' '");
                            }
                            $field = trim(substr($orderExpression, 0, $p));
                            $orderBy[] = [$field, $dirValue];
                        }
                    }
                    else {
                        $field = $def;
                        $dir = $ord['direction'];
                        $orderBy[] = [$field, $dir];
                    }
                }
            }
            else {
                throw new Exception("Invalid ORDER field: $field, ");
            }
        }
        return $orderBy;
    }

    /**
     * get Models by query parameters
     *
     * @param null $options -- SQL parts (all of them are optional. Default returns all models)
     *  - joins -- array of [type, tableName, onCondition] triplets. OnCondition is similar to where.
     *  - where -- array of SQL expressions to be AND-ed
     *  - orders -- array of order-by expressions or a single one
     *  - limit -- limit value or null
     *  - offset -- offset value or null
     *  - params -- array of query parameters (':'-parameters in the expressions)
     * @return Query
     * @throws Exception -- when $options is not array
     */
    public static function createQuery($options=[]) {
        $query = new Query($options);
        $query->asModel(get_called_class());
        return $query;
    }

    /**
     * Returns an array with [id=>name] pairs of all instances of this Model
     *
     * @throws Exception
     */
    public static function getIdsAndTexts($nameField='name', $order=null) {
        return ArrayHelper::map(static::getAll(null, $order), 'id', $nameField);
    }

    /**
     * Returns the value converted for use in database operations
     *
     * @throws Exception
     */
    public static function databaseValue($value) {
        if($value===null) return null;
        if(is_scalar($value)) return $value;
        if($value instanceof DateTime) {
            $format = $value->format('H:i:s')=='0:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s';
            return $value->format($format);
        }
        throw new Exception('Database value of ' . (is_object($value) ? get_class($value): gettype($value)) . ' is not defined');
    }

    public function getConnection() {
        return $this->_connection ?: App::$app->connection; // A model may have no connection
    }

    public function setConnection($connection) {
        $this->_connection = $connection;
    }

    /**
     * Called after the model is loaded from database (using any Model-returning Query or Model::getOne, Model::getAll).
     * Override this method to initialize further fields of the Model after loading from the database.
     * Useful for stored-computed fields.
     * The default implementation does nothing.
     * Note: attibute values calculated here not stored automatically in old-attributes
     *
     * @return void
     */
    public function afterLoad() {
    }
}
