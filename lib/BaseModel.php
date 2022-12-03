<?php

namespace uhi67\umvc;

use DateTime;
use Exception;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Basic data model (without database connection).
 * Model is the derived class of this.
 *
 * Implements
 *  - basic attribute handling
 *  - attribute labels
 *  - mass attribute assign
 *  - validation
 *
 * @property array $attributes -- all attribute values indexed by attribute name
 * @property-read array $errors -- field-name indexed array of numeric indexed error messages
 * @package UMVC Simple Application Framework
 */
class BaseModel extends Component implements JsonSerializable
{
    // These constants are used in validators
    const VALID_EMAIL = '/^\w+[\w\-.]*@\w+[\w\-.]+$/';
    const VALID_URL = '/^(https?|ftp):\/\/[^\s\/$.?#].[^\s]*$/iS';

    /** @var array[] $_attributes -- attributes are memory-cached */
    private static $_attributes=[];

    /** @var array[] $_errors -- validation errors indexed by field-name */
    private $_errors =[];

    /**
     * Returns the list of all attribute names of the model.
     * This database-less model implementation returns names of public properties of the class.
     *
     * @return array list of attribute names.
     * @throws Exception
     */
    public static function attributes() {
        if(static::class == 'app\lib\BaseModel') throw new Exception('Call this from a derived class.');
        if(array_key_exists(static::class, self::$_attributes)) return self::$_attributes[static::class];
        $class = new ReflectionClass(static::class);
        $names = array();
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $names[] = $property->getName();
            }
        }
        self::$_attributes[static::class] = $names;
        return $names;
    }

    /**
     * Returns a value indicating whether the model has an attribute with the specified name.
     *
     * @param string $name the name of the attribute
     *
     * @return bool whether the model has an attribute with the specified name.

     * @throws Exception
     */
    public static function hasAttribute($name) {
        Assertions::assertString($name);
        return in_array($name, static::attributes(), true);
    }

    /**
     * ## Must return the attribute labels.
     * Attribute labels are mainly used for display purpose
     * Order of labels is the default order of fields.
     * The default implementation returns empty array.
     * If a label is not defined, the humanized field-name will be used (converted to uppercase words).
     *
     * For getting label for a specific attribute, see {@see attributeLabel()}
     *
     * @return array attribute labels (name => label)
     */
    public static function attributeLabels() {
        return [];
    }

    /**
     * Returns the text label for the specified attribute.
     *
     * The label definitions are returned by {@see attributeLabels()}.
     * If a label is not defined:
     * - _id postfix is omitted.
     * - only first part of any .-composition is used
     * - or the humanized field-name will be used (converted to uppercase words).
     *
     * @param string $attribute the attribute name
     * @return string the attribute label
     * @throws Exception
     * @see attributeLabels()
     */
    public static function attributeLabel($attribute) {
        $labels = static::attributeLabels();
        if(isset($labels[$attribute])) return $labels[$attribute];
        if(substr($attribute,-3)=='_id') return static::attributeLabel(substr($attribute, 0,-3));
        if($p = strpos($attribute, '.')) return static::attributeLabel(substr($attribute, 0,$p));
        return AppHelper::humanize($attribute);
    }

    /**
     * Must return the validation rules by fields.
     *
     * [field-name => [rule1, [rule2, params], ...], ...]
     *
     * The default implementation is empty.
     * Call `$model->validate()` to performs the validation.
     * The rule may be a predefined rule or any user-defined method named `validateMyRule($fieldName, $params):bool`
     *
     * ### Predefined rules of Model:
     *
     * - 'mandatory' -- field is not null and not empty string
     * - 'nullable' -- empty value is stored as NULL
     * - ['default', value] -- always passes, replaces null value to default.
     * - 'defaultNow' -- for date/time fields, always passes, replaces null value to current timestamp.
     * - 'int' -- accepts any value is convertible into integer. Replaces the value with a valid integer.     * - ['length', min, max]
     * - 'lowercase' -- always passes, converts to lowercase
     * - 'trim'  -- always passes, removes surrounding whitespaces
     * - ['pattern', pattern(s)] -- valid if at least one of RE patterns is valid (second level: all of them)
     * - ['between', lower, upper] -- valid value between (including) limits
     * - ['length', lower, upper] -- valid value length is between (including) limits
     * - 'email'
     * - 'url'
     * - 'unique' -- field or field names have to be unique in the table
     *
     * See {@see Model::validate()} to perform validation.
     */
    public static function rules() {
        return [];
    }

    /**
     * Returns the value of a property
     *
     * 0. If the property is a public class property, we won't get here
     * 1. 'ref.field' style references are supported (called from getAttributes) (not implemented in setter)
     * 2. getters are handled by the parent Component
     *
     * @param string $name the property name
     * @return mixed the property value

     * @throws Exception if the property is not defined
     * @throws ReflectionException
     */
    public function __get($name) {
        // Reference composition (e.g. 'ref.name')
        if(strpos($name, '.')) {
            return ArrayHelper::getValue($this, $name);
        }
        // Other
        return parent::__get($name);
    }

    /**
     * Returns attribute values.
     *
     * @param array $names -- list of attributes whose value needs to be returned.
     * Default all attributes will be returned. (Can be read as '->attribute' property).
     *
     * @return array attribute values (name => value).
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAttributes($names = null) {
        $values = [];
        if ($names === null) {
            $names = static::attributes();
        }
        foreach ($names as $name) {
            $values[$name] = $this->$name;
        }
        return $values;
    }

    /**
     * ## Sets the attribute values in a massive way
     *
     * Skips unknown attributes without error.
     * Does not refresh preloaded referenced models.
     *
     * @param array $values -- associative attribute values (name => value) to be assigned to the model.
     * @return static
     *
     * @throws Exception
     * @see refreshRelated()
     * @see attributes()
     */
    public function setAttributes($values) {
        if (is_array($values)) {
            $attributes = array_flip($this->attributes());
            foreach ($values as $name => $value) {
                if (isset($attributes[$name])) {
                    /** @noinspection PhpVariableVariableInspection */
                    $this->$name = $value;
                }
            }
        }
        return $this;
    }

    /**
     * ## Sets the named attribute value.
     *
     * @param string $name the attribute name
     * @param mixed $value the attribute value.
     *
     * @throws Exception
     */
    public function setAttribute($name, $value) {
        Assertions::assertString($name);
        /** @noinspection PhpVariableVariableInspection */
        $this->$name = $value;
    }

    /**
     * Loads data of the source array, e.g. $_POST.
     *
     * @param array $source -- source array to load from
     * @param string|null $instanceName -- default is table name of the Model
     *
     * @return bool -- the model data is loaded from the source
     * @throws Exception
     */
    public function loadFrom(array $source, $instanceName=null) {
        if($instanceName===null) $instanceName = static::tableName();
        if ($instanceName === '' && !empty($source)) {
            $this->setAttributes($source);
            return true;
        } elseif(isset($source[$instanceName])) {
            $this->setAttributes($source[$instanceName]);
            return true;
        }
        return false;
    }

    /**
     * Inserts field-name and its error message into $errors array.
     *
     * @param string $fieldName -- the name of field
     * @param string $message ($1 is a placeholder for field name)
     *
     * @return false -- always
     * @throws Exception
     */
    public function addError($fieldName, $message) {
        $message = str_replace('$1', $fieldName, $message);
        if(!isset($this->_errors[$fieldName])) $this->_errors[$fieldName] = [];
        $this->_errors[$fieldName][] = $message;
        return false;
    }

    public function hasError() {
        return !empty($this->_errors);
    }

    /**
     * Returns the validation error messages of a field or all fields (default)
     *
     * @param string|null $fieldName
     *
     * @return array[] -- ['fieldName'=>['message', ...], ...], or ['message', ...] if a fieldName is specified
     */
    public function getErrors($fieldName=null) {
        if(!$fieldName) return $this->_errors;
        return $this->_errors[$fieldName] ?? [];
    }

    public function resetErrors() {
        $this->_errors = array();
    }

    /**
     * ## Validates the model data.
     *
     * Invalid data returns false and {@see Model::$errors} will contain the validation failures.
     *
     * The default implementation validates against user defined {see rules()}.
     * If you override it, and want to use predefined rules as well, don't forget to call parent::validate()
     *
     * For syntax of rule definitions, and the list of built-in validation methods, see {@see Model::rules()}
     *
     * @param array $attributeNames -- if an array is specified, validates only given fields (ignores unknown field names)
     *
     * @return boolean -- true if data is valid and may be saved to the database.
     * @throws Exception
     */
    public function validate($attributeNames=null) {
        $this->_errors = [];
        $valid = true;
        $rules = static::rules();
        foreach($rules as $field=>$def) {
            if($def===null) continue; // Overridden rule may be null
            // Global rules (skips if individual fields are specified)
            if(is_numeric($field)) {
                if($attributeNames) continue;
                // Global rule is 'ruleName' or ['ruleName', arg1, arg2, ...]
                $ruleName = is_array($def) ? array_shift($def) : $def;
                Assertions::assertString($ruleName);
                $args = is_array($def) ? $def : [];
                $functionName = 'validate'.AppHelper::camelize($ruleName);
                if(!is_callable([$this, $functionName])) throw new Exception("Validator function `$functionName` is missing");
                if(!call_user_func_array([$this, $functionName], array_merge([null], $args))) {
                    $valid = false;
                }
            }
            else if(!$attributeNames || is_array($attributeNames) && in_array($field, $attributeNames)) {
                $valid = $this->validate_rules($field, $def) && $valid; // Order is important!
            }
        }
        return $valid;
    }

    /**
     * @param string $fieldName
     * @param array $def -- array of multiple rules to validate against
     *
     * @return bool
     * @throws Exception
     */
    public function validate_rules($fieldName, $def) {
        $valid = true;
        Assertions::assertArray($def);
        foreach($def as $rule) {
            $ruleName = is_array($rule) ? $rule[0] : $rule;
            try {
                $valid = ($valid1 = $this->validate_rule($fieldName, $rule)) && $valid;
            }
            catch (Exception $e) {
                throw new Exception('Invalid rule '.$ruleName.': '.$e->getMessage(), 0, $e);
            }
            $ruleName = is_array($rule) ? array_shift($rule) : $rule;
            if(!$valid1 && $ruleName=='mandatory') break; // If mandatory failed, no more check.
        }
        return $valid;
    }

    /**
     * Validates a field against a rule definition
     *
     * @param string $fieldName
     * @param string|array $rule -- rule-name or array(ruleName, params...)
     *
     * @return bool
     * @throws Exception
     */
    public function validate_rule($fieldName, $rule) {
        $ruleName = is_array($rule) ? array_shift($rule) : $rule;
        $functionName = 'validate'.AppHelper::camelize($ruleName);
        if(!ctype_alnum($functionName)) throw new Exception("Invalid validator rule: `$ruleName`.");
        $args = is_array($rule) ? $rule : array();
        if(!is_callable(array($this, $functionName))) throw new Exception("Validator function `$functionName` is missing");
        if(!call_user_func_array(array($this, $functionName), array_merge(array($fieldName), $args))) {
            return false;
        }
        return true;
    }

    /**
     * Validates a scalar or array length
     *
     * @param string $fieldName
     * @param int $minlength -- 0 or null to skip check
     * @param int $maxlength -- 0 or null to skip check
     *
     * @return bool
     * @throws Exception
     */
    public function validateLength($fieldName, $minlength=-1, $maxlength=null) {
        if($minlength===-1) throw new Exception('Missing min length argument in length validator rule');
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if($value===null) return true;

        if(is_array($value)) {
            if($maxlength && count($value)>$maxlength) return $this->addError($fieldName, App::l('umvc', 'must have at most {maxlen} elements', ['maxlen'=>$maxlength]));
            if($minlength && count($value)<$minlength) return $this->addError($fieldName, App::l('umvc', 'must have at least {minlen} elements', ['minlen'=>$minlength]));
            return true;
        }

        if(!is_scalar($value)) return $this->addError($fieldName, '$1 is not a scalar');
        if($maxlength && strlen($value)>$maxlength) return $this->addError($fieldName, App::l('umvc', 'must be at most {maxlen} characters long', ['maxlen'=>$maxlength]));
        if($minlength && strlen($value)<$minlength) return $this->addError($fieldName, App::l('umvc', 'must be at least {minlen} characters long', ['minlen'=>$minlength]));
        return true;
    }

    /**
     * ## Validates any integer-like string
     * - Ignores empty string and null
     * - Accepts float if not exceeds PHP_INT_MAX and converts (truncates) to string
     * - Accepts also boolean and converts to 0/1
     * - Converts to string
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateInt($fieldName) {
        if($this->$fieldName=='') $this->$fieldName=null;
        $value = $this->$fieldName;
        if($value===null) return true;
        if(is_int($value)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = (string)$value;
            return true;
        }
        if(is_numeric($value) && is_float($value + 0)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = number_format(floor($value), 0, '', '');
            return true;
        }
        if(is_bool($value)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = ($value ? '1': '0');
            return true;
        }
        if(!is_string($value) || !preg_match('~^((:?+|-)?[0-9]+)$~', $value)) {
            return $this->addError($fieldName, App::l('umvc','is invalid integer'));
        }
        return true;
    }

    /**
     * Converts a string to lowercase
     *
     * Always passes
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateLowercase($fieldName) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if($value===null) return true;
        if(!is_string($value)) return true;
        /** @noinspection PhpVariableVariableInspection */
        $this->$fieldName = strtolower($value);
        return true;
    }

    /**
     * Trims whitespaces
     *
     * @param string $fieldName
     *
     * @return boolean
     * @throws Exception
     */
    function validateTrim($fieldName) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if($value===null) return true;
        if(!is_string($value)) return true;
        /** @noinspection PhpVariableVariableInspection */
        $this->$fieldName = trim($value);
        return true;
    }

    /**
     * If value is empty, defaults to current timestamp.
     * Fails if not a datetime
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateDefaultNow($fieldName) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if($value instanceof DateTime) return true;
        if($value===null || $value==='') {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = new DateTime();
            return true;
        }
        return $this->validateDatetime($fieldName);
    }

    /**
     * ## Validates a field using preg pattern (use // delimiters)
     *
     * Field type must be string or convertible to string
     * Null values always pass!
     *
     * @param string $field
     * @param string $pattern
     * @param string $customMessage
     *
     * @return bool
     * @throws Exception
     */
    public function validatePattern($field, $pattern, $customMessage=null) {
        $value = $this->$field;
        if(is_null($value)) return true;
        try {
            Assertions::assertString($pattern);
            if(preg_match($pattern, $value)==1) return true;
            return $this->addError($field, $customMessage ?: 'has invalid format');
        }
        catch(Exception $e) {
            throw new Exception(sprintf('Field `%s`: %s', $field, $e->getMessage()), 0, $e);
        }
    }

    /**
     * ## OR - Validates a field using multiple preg pattern (use // delimiters)
     *
     * Field type must be string or convertible to string
     * Null values always pass!
     * If any of patterns passes, the validation passes.
     *
     * @param string $field
     * @param string[] $patterns
     * @param string $customMessage
     *
     * @return bool
     * @throws Exception
     */
    public function validatePatterns($field, $patterns, $customMessage=null) {
        $value = $this->$field;
        if(is_null($value)) return true;
        try {
            Assertions::assertArray($patterns);
            foreach($patterns as $pattern) {
                Assertions::assertString($pattern);
                if(preg_match($pattern, $value) == 1) return true;
            }
            return $this->addError($field, $customMessage ?: App::l('umvc','has invalid format'));
        }
        catch(Exception $e) {
            throw new Exception(sprintf('Field `%s`: %s', $field, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Checks if field value is not empty
     *
     * @param string $fieldName
     *
     * @return boolean
     * @throws Exception
     */
    public function validateMandatory($fieldName) {
        $value = $this->$fieldName;
        if($value !== false && empty($value)) {
            return $this->addError($fieldName, App::l('umvc', 'is mandatory'));
        }
        return true;
    }

    /**
     * Converts empty string to null.
     * Always passes.
     *
     * @param string $fieldName
     * @return true
     */
    public function validateNullable($fieldName) {
        $value = $this->$fieldName;
        if($value === '') $this->$fieldName = null;
        return true;
    }

    /**
     * Checks if field value is between given limits (including)
     * null limits are ignored
     *
     * @param string $fieldName
     * @param mixed $min
     * @param mixed $max
     *
     * @return boolean
     * @throws Exception
     */
    public function validateBetween($fieldName, $min, $max=null) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if(is_null($value)) return true;
        $valid = ($min===null || $value >= $min) && ($max===null || $value <= $max);
        return $valid || $this->addError($fieldName, App::l('umvc','must be between {min} and {max}', ['min'=>$min, 'max'=>$max]));
    }

    /**
     * Special validator which always passes.
     * Modifies empty values to given default.
     *
     * @param string $fieldName
     * @param mixed $default -- may be a callable($model)
     *
     * @return true
     */
    public function validateDefault($fieldName, $default) {
        /** @noinspection PhpVariableVariableInspection */
        $value = $this->$fieldName;
        if(empty($value)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = is_callable($default) ? call_user_func($default, $this) : $default;
        }
        return true;
    }

    /**
     * Validates field as e-mail address
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateEmail($fieldName) {
        return $this->validatePattern($fieldName, self::VALID_EMAIL, 'is invalid e-mail address');
    }

    /**
     * Validates field as url address
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateUrl($fieldName) {
        return $this->validatePattern($fieldName, self::VALID_URL, 'is invalid url address');
    }

    /**
     * Validates field as e-mail OR url address
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateEmailOrUrl($fieldName) {
        return $this->validatePatterns($fieldName, [self::VALID_URL, self::VALID_EMAIL], 'is invalid url or e-mail address');
    }

    /**
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateDate($fieldName) {
        if($this->$fieldName=='') $this->$fieldName=null;
        $value = $this->$fieldName;
        if($value===null) return true;
        if($value instanceof DateTime) return true;
        $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y', 'Y. m. d.', 'Y.m.d.', 'Y.m.d'];
		foreach($formats as $format) {
            $value1 = DateTime::createFromFormat($format, $value);
            if($value1) break;
        }
        if($value1!==false) {
            $value1->setTime(0,0);
            $this->$fieldName = $value1;
            return true;
        }
        return $this->addError($fieldName, App::l('umvc','is invalid date'));
    }

    /**
     * Validates several datetime formats.
     * Replaces value with a DateTime object
     *
     * @param string $fieldName
     *
     * @return bool
     * @throws Exception
     */
    public function validateDatetime($fieldName) {
        if($this->$fieldName=='') $this->$fieldName=null;
        $value = $this->$fieldName;
        if($value===null) return true;
        if($value instanceof DateTime) return true;
        Assertions::assertString($value);
        $formats = array(DateTime::ATOM, 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y.m.d. H:i:s', 'd-M-y H:i:s', 'Y.m.d. H:i', 'd-M-y H:i',
            'Y.m.d.', 'd-M-y', 'Y. m. d. H:i', 'Y. m. d.');
        foreach($formats as $i=>$format) {
            $v = DateTime::createFromFormat($format, $value);
            if($v!==false) {
                if($i > 6) $v->setTime(0, 0);
                break;
            }
        }
        if($v!==false) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$fieldName = $v;
            return true;
        }
        return $this->addError($fieldName, App::l('umvc','is invalid date and time'));
    }

    /**
     * ## Converts the model into an array
     *
     * If `$recursive` is true, embedded objects will also be converted into arrays.
     *
     * @param array $fields the fields being requested. If empty, all attributes
     * @param bool $recursive whether to recursively return array representation of embedded objects.
     *
     * @return array the associative array representation of the object
     * @throws ReflectionException
     * @throws Exception
     */
    public function toArray($fields = null, $recursive = false) {
        if(!$fields) $fields = static::attributes();
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->$field;
        }
        return $recursive ? ArrayHelper::toArray($data) : $data;
    }

    /**
     * Serializes model data for json_encode
     *
     * @return array
     * @throws ReflectionException
     */
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * Field is safe to use if got from the end-user
     *
     * @param $field
     * @return bool
     */
    public function isSafe($field) {
        return array_key_exists($field, static::rules());
    }
}
