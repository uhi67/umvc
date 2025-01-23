<?php

namespace uhi67\umvc;

use Exception;

/**
 * Data type assertions to make the application secure and coherent
 *
 * @package UMVC Simple Application Framework
 */
class Assertions
{
    /**
     * If argument is not a string (or number), throws exception
     *
     * @param mixed $x
     * @param bool $null -- null is allowed
     *
     * @return void
     * @throws Exception
     */
    static function assertString($x, $null = false)
    {
        if ($null && $x === null) {
            return;
        }
        if (!is_string($x) && !is_numeric($x)) {
            throw new Exception('#1:parameter must be string, got ' . static::typeName($x));
        }
    }

    /**
     * If argument is not a valid integer (including string format), throws exception
     *
     * @param mixed $x
     *
     * @return void
     * @throws Exception
     */
    static function assertInt($x)
    {
        if (!is_scalar($x) || strval($x) != strval(intval($x))) {
            throw new Exception('#1:parameter must be int, got ' . static::typeName($x));
        }
    }

    /**
     * If argument is not an array, throws exception
     *
     * @param mixed $x
     *
     * @return void
     * @throws Exception -- when not array provided
     */
    static function assertArray($x)
    {
        if (!is_array($x)) {
            throw new Exception('#1:parameter must be an array, got ' . static::typeName($x));
        }
    }

    /**
     * Assures that $entity is an instance of $class or null
     *
     * @param object $entity
     * @param string $class
     * @param bool $null may be null
     *
     * @return void
     * @throws Exception
     */
    static function assertClass($entity, $class, $null = true)
    {
        if ($null && $entity === null) {
            return;
        }
        if ($entity === null) {
            throw new Exception("Parameter must not be null");
        }
        if (!is_object($entity)) {
            throw new Exception("Parameter must be object of $class, got " . gettype($entity));
        }
        if (!is_a($entity, $class)) {
            throw new Exception("Parameter must be a $class, got " . get_class($entity));
        }
    }

    /**
     * Returns classname or typename of the value
     *
     * @param $value
     * @return string
     */
    static function typeName($value)
    {
        if (!is_object($value)) {
            return gettype($value);
        }
        return get_class($value);
    }
}
