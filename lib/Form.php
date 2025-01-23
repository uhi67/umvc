<?php

namespace uhi67\umvc;

use Exception;

/**
 * Form class is a simple widget, currently a simple wrapper for the field method.
 *
 * **Example**
 *
 * The following example renders some fields of different types in a form:
 *
 * ```php
 *     $form = new Form([
 *         'model'=>$course,
 *         'layout'=>'horizontal',
 *         'labelClass' => 'col-md-4',
 *         'wrapperClass' => 'col-md-6 col-xl-4',
 *     ]);
 *     echo '<form class="form" action="" method="post" id="course_form" enctype="multipart/form-data">';
 *     echo $form->field('title', ['options'=>['required'=>true]]);
 *     echo $form->field('description', ['type'=>'textarea', 'options'=>['required'=>true, 'rows'=>8]]);
 *     echo $form->field('discipline_id', ['type'=>'select', 'items'=> Discipline::getIdsAndTexts()]);
 *     echo $form->field('mobility_format_id', ['type'=>'radiolist', 'items'=> MobilityFormat::getIdsAndTexts()]);
 *     echo '</form>';
 * ```
 *
 * @package UMVC Simple Application Framework
 */
class Form extends Component
{
    /** @var $model -- The model instance to display fields of, or null if the form is model-independent or model is specified at fields */
    public $model;
    /** @var string $layout -- null (default, a simple layout), or horizontal (label and value is in the same line) */
    public $layout;
    /** @var string $template -- The partial view file for the fields, may be computed from layout. Default is '_form/_field' */
    public $template;
    /** @var string|array $labelClass -- additional classname(s) for all field labels */
    public $labelClass = '';
    /** @var string|array $noticeClass -- additional classname(s) for all field notices */
    public $noticeClass = '';
    /** @var string|array $wrapperClass -- classname(s) for the wrapper div around the input part (needed for horizontal) */
    public $wrapperClass = '';

    /**
     * @throws Exception
     */
    public function init()
    {
        if ($this->layout) {
            $this->template = '_form/_field_' . $this->layout;
        }
        $viewFile = App::$app->basePath . '/views/' . $this->template . '.php';
        if (!file_exists($viewFile)) {
            $viewFile = dirname(__DIR__) . '/views/' . $this->template . '.php';
        }
        if (!file_exists($viewFile)) {
            throw new Exception("Form: template view file `$viewFile` for `$this->layout` does not exist.");
        }
    }

    /**
     * Renders a complete form field
     *
     * Available options:
     *  - type -- the input type, default is 'text'. Other values: hidden, password, checkbox, checkboxlist, radio, textarea, select, select2 (later)
     *  - modelName -- the name of the model instance. Default is the tablename of the model.
     *  - name -- the name of the HTML field. Default is 'tablename[fieldName]'
     *  - id -- the id attribute of the input tag, default is 'field-tablename-fieldName'
     *  - label -- the label text, default is the attribute label defined in the Model class
     *  - layout -- null for default, or 'horizontal' or any other layout defined as partial view named '_form/_field_layout'
     *  - template: the partial view to use, default is '_form/_field'. Effective only if layout is not defined.
     *  - class -- the additional classnames for the input tag
     *  - divClass -- the additional classnames for the enclosing div
     *  - icon -- 'glyphicon glyphicon-xxx' or 'fa fa-xxx' -- icon prepended to the label
     *  - value -- the value of the input. Default is $model->fieldName
     *  - items -- only for select, radio, checkboxlist
     *  - options -- any other HTML attributes for the input tag, e.g. readonly, placeholder, disabled, required
     *  - divOptions -- any other HTML attributes for the enclosing div tag
     *  - hint -- a helper text under the field input
     *
     * 'select' and 'radiolist' field types are rendered considering 'required' and 'placeholder' options. If the field is not required, an empty selection option is included.
     *
     * @param string|null $fieldName -- the fieldname of the model (property) or name of the standalone field. May be null for button type
     * @param array $options -- other options, see above
     * @param Model|null $model -- the model instance to display a field of, or false (standalone field)
     *
     * @throws Exception
     */
    public function field($fieldName, array $options = [], $model = null)
    {
        if ($model === null) {
            $model = $this->model;
        }
        $field = new Field(array_merge([
            'model' => $model,
            'fieldName' => $fieldName,
            'form' => $this,
            'template' => $this->template,
        ], $options));
        return $field->render();
    }
}
