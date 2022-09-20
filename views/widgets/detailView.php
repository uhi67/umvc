<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @var App $this */
/** @var array $options -- main table HTML attributes */
/** @var array|BaseModel $model -- the object or array to display */
/** @var array $attributes -- the attribute configuration indexed by attribute names to display  */

/**
 * Available attribute configuration settings
 * - options: HTML attributes of `<tr>` of the attribute
 * - label: Label to display, default is model attribute label or humanized array index
 * - value: value to display, default is model value. Can be a callable function($name, $model)
 */

use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use uhi67\umvc\ArrayHelper;
use uhi67\umvc\BaseModel;
use uhi67\umvc\Html;

if(isset($attributes) && !is_array($attributes) && !($model instanceof BaseModel)) throw new Exception('$model must be an array or a BaseModel');
$attributeNames = is_array($model) ? array_keys($attributes) : $model->attributes();
if(!isset($attributes)) $attributes = array_combine($attributeNames, array_map(function($item) { return [

]; }, $attributeNames));

if(!$options) $options = ['class'=>'table detail-view'];

$rows = '';
foreach($attributes as $name => $attribute) {
	$attributeOptions = ArrayHelper::getValue($attribute, 'options', []);
	$attributeLabel = ArrayHelper::getValue($attribute, 'label', $model instanceof BaseModel ? $model->attributeLabel($name) : AppHelper::humanize($name));
	$attributeValue = ArrayHelper::getValue($attribute, 'value', ArrayHelper::getValue($model, $name, App::l('umvc', 'not set')));
	if(is_callable($attributeValue)) $attributeValue = call_user_func($attributeValue, $name, $model);
	$rows .= Html::tag('tr',
			Html::tag('th', $attributeLabel) .
			Html::tag('td', $attributeValue),
	$attributeOptions);
}
echo Html::tag('table', $rows, $options);



