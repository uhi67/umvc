<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use DateTime;
use Exception;

/**
 * A displayable field of a form. Used by Form::field().
 * The purpose is the proper rendering within an HTML form.
 *
 * @property-read string $textValue -- The value of the field converted to string
 * @property-read string $requiredMark -- The * HTML-chunk with Required title if the field is required
 * @package UMVC Simple Application Framework
 */
class Field extends Component
{
    /** @var Model $model -- The model of the field or null if standalone field is to be created */
    public $model;
    /** @var string $fieldName -- the name of the property of the Model (database field) */
    public $fieldName;
    /** @var string $modelName -- the name of the model instance. Default is the tablename of the model. */
    public $modelName;
    /** @var string $divClass -- the additional classnames for the enclosing div */
    public $divClass = '';
    /** @var string $label -- the label text, default is the attribute label defined in the Model class */
    public $label;
    /** @var string|array $labelClass -- the additional classnames for the field label */
    public $labelClass = '';
    /** @var string $notice -- a notice text between the label and the input */
    public $notice;
    /** @var string|array $noticeClass -- the additional classnames for the notice if exists */
    public $noticeClass = '';
    /** @var string|array $wrapperClass -- the classnames for the input wrapper div */
    public $wrapperClass = '';
    /** @var string $name -- the name of the HTML field. Default is 'tablename[fieldName]' */
    public $name;
    /** @var string $id -- the id attribute of the input tag, default is 'field-tablename-fieldName' */
    public $id;
    /** @var string $type -- the input type, default is 'text'. Other values: password, checkbox, radio, textarea, select, select2 (later) */
    public $type = 'text';
    /** @var string $class -- the additional classnames for the input tag */
    public $class = '';
    /** @var mixed $value -- the value of the input. Default is $model->fieldName */
    public $value;
    /** @var array $options -- any other HTML attributes for the input tag, e.g. readonly, placeholder */
    public $options = [];
    /** @var array $divOptions -- any other HTML attributes for the enclosing div tag */
    public $divOptions = [];
    /** @var array $items -- selectable items for select, radio, checkboxlist */
    public $items = [];
    /** @var string $icon -- 'glyphicon glyphicon-xxx' or 'fa fa-xxx' */
    public $icon;
    /** @var string $error -- The error message for the field or false if error message is disabled. If null (default) the modell error is used. */
    public $error;
    /** @var Form $form -- the form object this field belongs to */
    public $form;
    /** @var string $template -- the partial view name for the rendering. default is _form/_field */
    public $template;
    /** @var string $hint -- a helper text under the field input */
    public $hint;

    /**
     * Initializes the field
     *
     * @throws Exception
     */
    public function init() {
        if($this->model) {
            if(!$this->modelName) $this->modelName = $this->model->tableName();
            if(!$this->label) $this->label = $this->model->attributeLabel($this->fieldName);
            if($this->fieldName) {
                if(!$this->id) $this->id = 'field-' . $this->modelName . '-' . $this->fieldName;
                if(!$this->value) $this->value = $this->model->{$this->fieldName};
                if($this->error === null && $this->model->hasError()) {
                    $this->error = implode(', ', $this->model->getErrors($this->fieldName)) ?: false;
                }
            }
        }
        if(!$this->name) $this->name = $this->model ? $this->modelName.'['.$this->fieldName.']' : $this->fieldName;
        if($this->icon) $this->label = $this->icon().' '.$this->label;
        if(!$this->template) $this->template = '_form/_field';

        // Add validation rules to perform client-side validation (later...)
        if($this->model && $this->fieldName) {
            $rules = $this->model->rules();
            // Applicable client-side validations (not implemented yet)
            $clientRules = ['mandatory', 'length', 'email', 'url', 'int', 'date', 'pattern'];
            if(isset($rules[$this->fieldName])) {
                $fieldRules = array_filter($rules[$this->fieldName], function($v) use($clientRules) {
                    return in_array($v, $clientRules) || is_array($v) && in_array($v[0], $clientRules);
                });
                if(in_array('mandatory', $fieldRules) && !isset($options['required'])) {
                    $this->options['required'] = true;
                }
                $this->options['data-rules'] = json_encode($fieldRules);
            }
        }
    }

    public function icon() {
        if(!$this->icon) return '';
        if(substr($this->icon,0,2)=='fa') return '<i class="'.$this->icon.'"></i>';
        return '<span class="'.$this->icon.'"></span>';
    }

    /**
     * Computes the additional HTML options for the input tags
     *
     * @param null $options
     *
     * @return string
     */
    public function renderOptions($options=null) {
        if(!$options) $options = $this->options;
        $result = '';
        foreach($options as $name=>$value) {
            if($value===true) $value = null;
            if($value===false) continue;
            $result .= ' '.htmlspecialchars($name).'="'.htmlspecialchars($value).'"';
        }
        return $result;
    }

    /**
     * Renders the class tag for field label
     */
    public function labelClass() {
        $class = [];
        if($this->form->layout=='horizontal') $class[] = 'control-label';
        if(is_array($this->form->labelClass)) $class = array_merge($class, $this->form->labelClass);
        elseif($this->form->labelClass) $class[] = $this->form->labelClass;

        if(is_array($this->labelClass)) $class = array_merge($class, $this->labelClass);
        elseif($this->labelClass) $class[] = $this->labelClass;
        return $class ? ' class="'.implode(' ', $class).'"' : '';
    }

    /**
     * Renders the class tag for field notice
     */
    public function noticeClass() {
        $class = ['notice'];
        if(is_array($this->form->noticeClass)) $class = array_merge($class, $this->form->noticeClass);
        elseif($this->form->noticeClass) $class[] = $this->form->noticeClass;

        if(is_array($this->noticeClass)) $class = array_merge($class, $this->noticeClass);
        elseif($this->noticeClass) $class[] = $this->noticeClass;

        return $class ? ' class="'.implode(' ', $class).'"' : '';
    }

    /**
     * Renders the class tag for field input block wrapper div
     */
    public function wrapperClass() {
        $class[] = [];
        if(is_array($this->form->wrapperClass)) $class = array_merge($class, $this->form->wrapperClass);
        elseif($this->form->wrapperClass) $class[] = $this->form->wrapperClass;

        if(is_array($this->wrapperClass)) $class = array_merge($class, $this->wrapperClass);
        elseif($this->wrapperClass) $class[] = $this->wrapperClass;

        return $class ? ' class="'.implode(' ', $class).'"' : '';
    }

    /**
     * @throws Exception
     */
    public function render() {
        return App::$app->renderPartial($this->template, ['field'=>$this]);
    }

    /**
     * Renders the input field of all types
     *
     * @throws Exception
     */
    public function renderInput() {
        $functionname = 'renderInput'. AppHelper::camelize($this->type);
        if(!is_callable([$this, $functionname])) throw new Exception("Undefined input type `$this->type`.");
        return call_user_func([$this, $functionname]);
    }

    public function renderInputText() {
        return $this->renderInputDefault();
    }

    public function renderInputDefault() {
        $options = $this->renderOptions();
        return "<input type='$this->type' id='$this->id' name='$this->name' class='form-control $this->class' value='$this->textValue' $options aria-invalid='false' />";
    }

    public function renderInputDate() {
        return $this->renderInputDefault();
    }

    public function renderInputSelect() {
        $options = $this->renderOptions();
        $result = "<select id='$this->id' name='$this->name' class='form-control $this->class' $options aria-invalid='false'>";
        $required = $this->options['required'] ?? false;
        if(!$required || !$this->value) {
            $placeHolder = $this->options['placeholder'] ?? 'Please select one';
            $emptyText = $required ? $placeHolder : '';
            $result .= "<option value=''>$emptyText</option>";
        }
        foreach($this->items as $v=>$l) {
            $selected = in_array($v, (array)$this->value) ? 'selected' : '';
            $result .= "<option value='$v' $selected>$l</option>";
        }
        $result .= '</select>';
        return $result;
    }

    public function renderInputCheckbox() {
        $options = $this->renderOptions();
        $checked = $this->value ? 'checked' : '';
        return "
            <input type='hidden' id='$this->id-default' name='$this->name' value='0'/>
            <input type='checkbox' id='$this->id' name='$this->name' class='form-control $this->class' value='1' $checked $options aria-invalid='false' />\n";
    }

    public function renderInputRadiolist() {
        $options = $this->renderOptions();
        $result = "<input type='hidden' id='$this->id-default' name='$this->name' value='' />";
        $required = $this->options['required'] ?? false;
        if(!$required) {
            $emptyText = $this->options['placeholder'] ?? 'None';
            $checked = !$this->value;
            $result .= "<label for='$this->id-none'>
                    <input type='radio' id='$this->id-none' name='$this->name' class='$this->class' value='' $checked $options aria-invalid='false' />
                    $emptyText
                </label>\n";
        }
        foreach($this->items as $value=>$label) {
            $index = is_integer($value) ? $value : crc32($value);
            $checked = ($this->value===$value || ($this->value!==null && $value!==null && $this->value == $value)) ? 'checked' : '';
            $result .= "
                <label for='$this->id-$index'>
                    <input type='radio' id='$this->id-$index' name='$this->name' class='$this->class' value='$value' $checked $options aria-invalid='false' />
                    $label
                </label>\n";
        }
        return $result;
    }

    /**
     * Value must be a simple array of values. Multiple selection is allowed.
     *
     * @return string
     */
    public function renderInputCheckboxlist() {
        $options = $this->renderOptions();
        $result = "<input type='hidden' id='$this->id-default' name='$this->name' value='' />";
        foreach($this->items as $value=>$label) {
            $index = is_integer($value) ? $value : crc32($value);
            $checked = in_array($value, $this->value) ? ' checked' : '';
            $result .= "
                <label for='$this->id-$index'>
                    <input type='checkbox' id='$this->id-$index' name='$this->name' class='$this->class' value='$value' $checked $options aria-invalid='false' />
                    $label
                </label>\n";
        }
        return $result;
    }

    public function renderInputTextarea() {
        $options = $this->renderOptions();
        return "<textarea id='$this->id' name='$this->name' class='form-control $this->class' $options>$this->value</textarea>";
    }

    public function renderInputSubmit() {
        return "<button type='submit' id='$this->id' class='btn $this->class' value='$this->value'>$this->label</button>\n";
    }

    public function renderInputSelect2() {
        $options = $this->renderOptions();
        $result = "<select id='$this->id' name='$this->name[]' class='form-control select2 $this->class' $options aria-invalid='false'>";
        $required = $this->options['required'] ?? false;
        if(!$required || !$this->value) {
            $placeHolder = $this->options['placeholder'] ?? 'Please select one';
            $emptyText = $required ? $placeHolder : '';
            $result .= "<option value=''>$emptyText</option>";
        }
        foreach($this->items as $v=>$l) {
            $selected = in_array($v, (array)$this->value) ? 'selected' : '';
            $result .= "<option value='$v' $selected>$l</option>";
        }
        $result .= '</select>';
        return $result;
    }

    /**
     * Returns the value of the field converted to text to display in an input
     * Getter for {@see $textValue} property
     * @throws Exception
     */
    public function getTextValue() {
        if($this->value===true) return 'TRUE';
        if($this->value===false) return 'FALSE';
        if($this->value===null) return '';
        if(is_scalar($this->value)) return $this->value;
        if($this->value instanceof DateTime) {
            $format = $this->value->format('H:i:s')=='0:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s';
            return $this->value->format($format);
        }
        throw new Exception('Text value of ' . (is_object($this->value) ? get_class($this->value): gettype($this->value)) . ' is not defined');
    }

    /**
     * Getter for {@see $requiredMark} property
     * @return string
     */
    public function getRequiredMark() {
        $disabled = $this->options['disabled'] ?? false;
        return !$disabled && isset($this->options['required']) && $this->options['required'] ? '<b title="Required">*</b>' : '';
    }
}
