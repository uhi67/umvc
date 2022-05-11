<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use Closure;
use Exception;
use Traversable;

/**
 * Array-related helper functions
 *
 * @package UMVC Simple Application Framework
 */
class ArrayHelper {
	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * Converts an object or an array of objects into an array.
	 *
	 * The properties specified for each class is an array of the following format:
	 *
	 * ```php
	 * [
	 *     'Post' => [
	 *         'id',
	 *         'title',
	 *         // the key name in array result => property name
	 *         'createTime' => 'created_at',
	 *         // the key name in array result => anonymous function
	 *         'length' => function ($post) {
	 *             return strlen($post->content);
	 *         },
	 *     ],
	 * ]
	 * ```
	 *
	 * The result of `ArrayHelper::toArray($post, $properties)` could be like the following:
	 *
	 * ```php
	 * [
	 *     'id' => 123,
	 *     'title' => 'test',
	 *     'createTime' => '2013-01-01 12:00AM',
	 *     'length' => 301,
	 * ]
	 * ```
	 *
	 * @param object|array|string $object -- the object to convert into an array
	 * @param array $properties -- a mapping from object class names to the properties that must be put into the resulting arrays.
	 * @param bool $recursive -- whether to recursively convert properties which are objects into arrays.
	 * @return array -- the array representation of the object
	 */
	public static function toArray($object, $properties = array(), $recursive = true)
	{
		if (is_array($object)) {
			if ($recursive) {
				foreach ($object as $key => $value) {
					if (is_array($value) || is_object($value)) {
						$object[$key] = static::toArray($value, $properties);
					}
				}
			}

			return $object;
		} elseif (is_object($object)) {
			if (!empty($properties)) {
				$className = get_class($object);
				if (!empty($properties[$className])) {
					$result = array();
					foreach ($properties[$className] as $key => $name) {
						if (is_int($key)) {
							/** @noinspection PhpVariableVariableInspection */
							$result[$name] = $object->$name;
						} else {
							$result[$key] = static::getValue($object, $name);
						}
					}

					return $recursive ? static::toArray($result, $properties) : $result;
				}
			}
            if(is_callable([$object, 'toArray'])) {
                $result = $object->toArray();
            }
            else {
                // Reading default public properties
                $result = array();
                foreach ($object as $key => $value) {
                    $result[$key] = $value;
                }
            }
			return $recursive ? static::toArray($result, $properties) : $result;
		} else {
			return array($object);
		}
	}

	/**
	 * Retrieves the value of an array element or object property with the given key or property name.
	 * If the key does not exist in the array or object, the default value will be returned.
	 *
	 * A composite key may be specified as array like `['x', 'y', 'z']`.
     * If the key contains '.', and dotted key does not exist, a composite key is used, e.g 'aa.bb' will mean ['aa', 'bb']
	 *
	 * Examples
	 *
	 * ```php
	 * // working with array
	 * $username = ArrayUtils::getValue($_POST, 'username');
	 * // working with object
	 * $username = ArrayUtils::getValue($user, 'username');
	 * // working with anonymous function
	 * $fullName = ArrayUtils::getValue($user, function ($user, $defaultValue) {
	 *     return $user->firstName . ' ' . $user->lastName;
	 * });
	 * // using a composite key to retrieve the value:
	 * $value = ArrayUtils::getValue($order, ['article', 'name']); // returns $order->article->name or $order['article']['name'] (or null if any of the indices is missing)
	 * ```
	 *
	 * @param array|object $array -- array or object to extract value from
	 * @param string|Closure|array $key -- key name of the array element, an array of keys or property name of the object,
	 * or an anonymous function returning the value. The anonymous function signature should be:
	 * `function($array, $defaultValue)`.
	 * @param mixed $default -- the default value to be returned if the specified array key does not exist. Not used when
	 * getting value from an object.
	 *
	 * @return mixed -- the value of the element if found, default value otherwise
	 */
	public static function getValue($array, $key, $default = null) {
		if(is_null($array)) return $default;
		if ($key instanceof Closure) {
			return $key($array, $default);
		}

		if (is_array($key)) {
			$lastKey = array_pop($key);
			foreach ($key as $keyPart) {
				$array = static::getValue($array, $keyPart);
			}
			$key = $lastKey;
		}

		if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array)) ) {
			return $array[$key];
		}

		if (is_object($array)) {
            if($p=strpos($key, '.')) {
                $a1 = self::getValue($array, substr($key,0,$p));
                return self::getValue($a1, substr($key, $p+1), $default);
            }
			try {
				// this is expected to fail if the property does not exist, or __get() is not implemented
				/** @noinspection PhpVariableVariableInspection */
				return $array->$key;
			}
			catch(Exception $e) {
				return $default;
			}
		} elseif (is_array($array)) {
			if(isset($array[$key]) || array_key_exists($key, $array)) return $array[$key];
            if(($p=strpos($key, '.')) && array_key_exists($k1=substr($key,0,$p), $array)) {
                $a1 = self::getValue($array, $k1);
                if(is_array($a1) || is_object($a1)) return self::getValue($a1, substr($key, $p+1), $default);
            }
		}
		return $default;
	}

	/**
	 * Retrieves and removes the value of an array element with the given key.
	 * If the key does not exist in the array, the default value will be returned.
	 *
	 * The key must be scalar.
	 *
	 * Examples
	 *
	 * ```php
	 * // working only with array
	 * $username = ArrayUtils::fetchValue($_POST, 'username');
	 * ```
	 *
	 * @param array $array -- array to extract value from
	 * @param string $key -- key name
	 * @param mixed $default -- default return value when the specified array key does not exist
	 *
	 * @return mixed the value of the element if found, default value otherwise
	 * @throws Exception
	 */
	public static function fetchValue(&$array, $key, $default = null) {
		Assertions::assertArray($array);

		if(isset($array[$key]) || array_key_exists($key, $array)) {
			$result = $array[$key];
			unset($array[$key]);
			return $result;
		}
		return $default;
	}

	/**
	 * Check the given array if it is an associative array.
	 *
	 * If `$strict` is true, the array is associative if all its keys are strings.
	 * If `$strict` is false, the array is associative if at least one of its keys is a string.
	 *
	 * An empty array will be considered associative only in strict mode.
	 *
	 *    - `isAssociative(array, false)` means the array has associative elements.
	 *    - `!isAssociative(array, true)` means the array has non-associative elements.
	 *
	 * @param array $array the array being checked
	 * @param bool $strict the array keys must be all strings and not empty to be treated as associative.
	 * @return bool the array is associative
	 */
	public static function isAssociative($array, $strict=true) {
		if (!is_array($array)) return false;
		foreach ($array as $key => $value) {
			if (!is_string($key) && $strict) return false;
			if (is_string($key) && !$strict) return true;
		}
		return $strict;
	}

	/** @noinspection PhpMethodNamingConventionInspection */
	/**
	 * Finds first array element with key which satisfies $fn
	 *
	 * @param Traversable|array $aa
	 * @param callable $fn ($item, $key)
	 *
	 * @return false|int|string -- index of first match or false if not found
	 */
	public static function array_find_key($aa, $fn) {
		foreach($aa as $k=>$v) if($fn($v, $k)) return $k;
		return false;
	}

	/**
	 * Creates an associative array from an array of objects
	 * The original keys are ignored (except of null index)
	 * null indices will not generate output.
	 * Multiple indices with the same value yields the value of last occurrence
	 *
	 * @param array $objects
	 * @param string|callable|null $indexProp -- property name or callable($obj) for index, null will keep original key
	 * @param string|callable|null $valueProp -- property name or callable($obj) for value; in case of null the original object will return
	 *
	 * @return array -- mapped associative array
	 * @throws Exception
	 */
	public static function map($objects, $indexProp, $valueProp=null) {
		$result = array();
		if(!is_iterable($objects)) throw new Exception('`Objects` must be iterable', gettype($objects));
		foreach($objects as $key => $obj) {
			if($indexProp===null) $index = $key;
			else $index = is_callable($indexProp) ? call_user_func($indexProp, $obj) : static::getValue($obj, $indexProp);
			if($index !== null) {
				if(!is_scalar($index)) throw new Exception('Invalid index '.print_r($index, true));
				$result[$index] = is_null($valueProp) ?
					$obj :
					(is_callable($valueProp) ? call_user_func($valueProp, $obj) : static::getValue($obj, $valueProp));
			}
		}
		return $result;
	}

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     * @param array $a -- array to merge into
     * @param array $b -- array to merge from; additional arrays are allowed as per a variadic argument
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    public static function genUniqueIndex(array $array, string $prefix) {
        $i = 1;
        do {
            $r = $prefix . $i++;
        } while(array_key_exists($r, $array));
        return $r;
    }

    /**
     * Copies an array using a keymap definition.
     *
     * @param array $array -- the array to copy
     * @param array $keymap -- the keymap definition to use
     *  - each key in this array must be mapped to a replacement key
     *  - replacement keys and only these are used to initialize the new array
     *  (so keys in the input array that are not part of the keymap definition are ignored)
     *  - when a replacement key cannot be reached from the input array, its corresponding value will be null in the new array
     *
     * @return array
     * @author arlogy
     */
    public static function copyArrayMappingKeys($array, $keymap) {
        if(!is_array($array) || !is_array($keymap)) return [];
        $result = [];
        foreach($keymap as $k1 => $k2) {
            $result[$k2] = $array[$k1] ?? null;
        }
        return $result;
    }
}
