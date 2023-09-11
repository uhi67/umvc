<?php

namespace uhi67\umvc;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * # Component
 *
 * A base class for most of the others.
 *
 * - Implements **property** features: magic getter and setter uses getProperty and setProperty methods
 * - **Configurable**: constructor accepts a configuration array containing values for public properties
 *
 * All descendant of Component can be created using a *"configuration array"* (AKA options).
 * The configuration array is simply an associative array with values of public properties.
 * The writable virtual properties (accessed via setters) also configurable.
 *
 * **Example**
 * ```php
 * $query = new Query([
 *      'type' => 'UPDATE',
 *      'modelClass' => User:class,
 *      'values' => ['admin_type'=>'admin'],
 *      'where' => ['uid'=>'test1@test.test'],
 * ]);
 *```
 *
 * @property-read string $shortName -- unqualified class name
 * @package UMVC Simple Application Framework
 */
abstract class Component {

    /** @var Component|App $parent -- the parent component which created this object (The App itself for the config-defined components) */
    public $parent;

    /**
     * # Component constructor
     * The default implementation does two things:
     *
     * - Initializes the object with the given configuration `$config`.
     * - Calls init().
     *
     * If this method is overridden in a child class, it is recommended that
     *
     * - the last parameter of the constructor is a configuration array, like `$config` here.
     * - call the parent implementation in the constructor.
     *
     * @param array|mixed $config name-value pairs that will be used to initialize the object properties
     * @throws Exception -- when the config is not an array (or null)
     */
    public function __construct($config = []) {
        if (!empty($config)) {
            static::configure($this, $config);
        }
        $this->init();
    }

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration. The default implementation does nothing, override it if you want to use.
     * @return void
     */
    public function init() {
        // This function is intentionally empty. Descendants need not call it.
    }

    /**
     * Prepares the object.
     * This method is invoked during App creation after all components are initialized.
     * Default implementation does nothing, override it if you want to use.
     * @return void
     */
    public function prepare()
    {
        // This function is intentionally empty. Descendants need not call it.
    }

    /**
     * Returns the value of a component property.
     *
     * @param string $name the property name
     *
     * @return mixed the property value
     * @throws Exception
     * @see __set()
     */
    public function __get($name) {
        if(strpos($name, '-')!==false) $name = AppHelper::camelize($name);

        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (method_exists($this, 'set' . $name)) {
            throw new Exception('Getting write-only property: ' . get_class($this) . '::' . $name);
        }

        throw new Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Sets the value of a component property.
     *
     * @param string $name the property name
     * @param mixed $value the property value
     *
     * @throws Exception if the property is not defined or the property is read-only.
     * @see __get()
     */
    public function __set($name, $value) {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return;
        }
        if (method_exists($this, 'get' . $name)) {
            throw new Exception('Setting read-only property: ' . get_class($this) . '::' . $name);
        }
        throw new Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Checks if a property is set: defined and not null.
     * @param string $name the property name
     * @return bool whether the named property is set
     * @see http://php.net/manual/en/function.isset.php
     */
    public function __isset($name) {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }
        return false;
    }

    /**
     * Sets a component property to be null.
     *
     * @param string $name the property name
     *
     * @throws Exception if the property is read only.
     * @see http://php.net/manual/en/function.unset.php
     */
    public function __unset($name) {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter(null);
            return;
        }
        throw new Exception('Unsetting an unknown or read-only property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Returns a value indicating whether a property is defined for this component.
     * @param string $name the property name
     * @param bool $checkVars whether to treat member variables as properties
     * @return bool whether the property is defined
     * @see canGetProperty()
     * @see canSetProperty()
     */
    public function hasProperty($name, $checkVars = true) {
        return $this->canGetProperty($name, $checkVars) || $this->canSetProperty($name, false);
    }

    /**
     * Returns a value indicating whether a property can be read.
     * @param string $name the property name
     * @param bool $checkVars whether to treat member variables as properties
     * @return bool whether the property can be read
     * @see canSetProperty()
     */
    public function canGetProperty($name, $checkVars = true) {
        if (method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        }
        return false;
    }

    /**
     * Returns a value indicating whether a property can be set.
     * @param string $name the property name
     * @param bool $checkVars whether to treat member variables as properties
     * @return bool whether the property can be written
     * @see canGetProperty()
     */
    public function canSetProperty($name, $checkVars = true) {
        if (method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        }
        return false;
    }

    /**
     * Configures an object with the initial property values.
     *
     * @param Component $object the object to be configured
     * @param array|null $config the property initial values given in terms of name-value pairs.
     *
     * @return object the object itself
     * @throws Exception -- when the $config is not an array or null
     */
    public static function configure($object, $config=null) {
        if($config===null) return $object;
        Assertions::assertArray($config);
        foreach ($config as $name => $value) {
            #echo get_class($object)."::$name: ".session_status(). " <br/>";
            if($name == 'class' && !$object->hasProperty('class')) continue;
            /** @noinspection PhpVariableVariableInspection */
            $object->$name = $value;
        }
        return $object;
    }

    /**
     * Creates a new Component using the given configuration.
     *
     * You may view this method as an enhanced version of the `new` operator.
     * The method supports creating an object based on a class name, a configuration array or
     * an anonymous function.
     *
     * Below are some usage examples:
     *
     * ```php
     * // create an object using a class name
     * $object = Component::create('Customer');
     *
     * // create an object using a configuration array
     * $object = Component::create([
     *     'class' => Customer::class,
     *     'name' => 'Foo Bar',
     * ]);
     *
     * // create an object with two constructor parameters
     * $object = Component::create('Customer', [$param1, $param2]);
     * ```
     *
     * @param string|array|callable $type the object type. This can be specified in one of the following forms:
     *
     * - a string: representing the class name of the object to be created
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a configuration array: the array must contain a `0` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     *
     * @param array $params the constructor parameters
     *
     * @return object the created object
     * @throws Exception if the configuration is invalid.
     * @throws ReflectionException
     */
    public static function create($type, array $params = array()) {
        if (is_string($type)) {
            return static::createClass($type, $params);
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            return static::createClass($class, $type);
        } elseif (is_array($type) && isset($type[0])) {
            $class = $type[0];
            unset($type[0]);
            return static::createClass($class, $type);
        } elseif (is_array($type) && is_a(get_called_class(), Component::class, true) && get_called_class() !== Component::class) {
            $class = get_called_class();
            return static::createClass($class, $type);
        } elseif (is_callable($type, true)) {
            return call_user_func_array($type, $params);
        } elseif (is_array($type)) {
            throw new Exception('Object configuration must be an array containing a "class" element, or call from a derived Component class.');
        }
        throw new Exception('Unsupported configuration type: ' . gettype($type));
    }

    /**
     * @param $class
     * @param $config
     *
     * @return object
     * @throws ReflectionException
     */
    private static function createClass($class, $config) {
        $reflection = new ReflectionClass($class);
        return $reflection->newInstanceArgs(array($config));
    }

    public function getNode() {
        return null;
    }

    /**
     * Returns class name without namespace of the caller class.
     * The proper static call is static::shortName()
     *
     * @return string
     */
    public function getShortName() {
        $reflect = new ReflectionClass($this);
        return $reflect->getShortName();
    }

    /**
     * @param array $data -- name->value pairs to set
     * @param array|null $fields -- if given, a list of properties to set (filter/map: [field, field=>mapped, ...])
     */
    public function populate($data, $fields=null) {
        if($fields) {
            foreach($fields as $field=>$remote) {
                if(is_numeric($field)) $field = $remote;
                if($this->canSetProperty($field)) $this->$field = ArrayHelper::getValue($data, $remote);
            }
        } else {
            foreach($data as $name => $value) {
                if($this->canSetProperty($name)) $this->$name = $value;
            }
        }
    }

    /**
     * Returns class name without namespace.
     * If class or object is not specified, uses the name of the current late binding static class.
     *
     * @param string|object $class -- FQN class name or an object
     *
     * @return string
     */
    public static function shortName($class=null) {
        if(!$class) $class = get_called_class();
        if(is_object($class)) {
            $reflect = new ReflectionClass($class);
            return $reflect->getShortName();
        }
        $p = strrpos($class, '\\');
        if($p===false) return $class;
        return substr($class, $p+1);
    }
}
