<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

/** @noinspection PhpUnused */

namespace educalliance\umvc;

use Exception;

/**
 * Represents a column in the table.
 *
 * Used internally by Grid widget.
 *
 * Renders
 *  - column header, with sorting options
 *  - search field
 *  - row value cell
 *
 * ### The configuration options of the Column
 * - string|null **$field** -- the field-name of the model to display. May be null if a not-model column is displayed
 * - string **$width** -- the CSS value of the column's with attribute, may be "12%" or "250px"
 * - string|null|false **$label** -- the label to display. Default is the model's label associated with the field. False to no label.
 * - null|string|callable **$value** -- other (compound) field-name or `function(Model $model):string` which computes the value of the column -- the default is the value of the field of the model
 * - bool|string **$filter** -- the column is filtered, or custom filter cell content
 * - bool|string **$order** -- the column is ordered, or custom order clauses separated by ; and ASC is the first
 * - bool|string **$searchIcon** -- search icon to display before search input. Default is none. True = built-in magnifier icon.
 * - bool **$searchCancel** -- display a search-cancel icon in the search lane
 * - string **$class** -- custom class for value cell
 * - string **$headerClass** -- custom header class
 * - string|Model **$model** -- the model name used in the table
 * - string|bool **$hint** -- title (hint displayed at mouse hover) default is original label if label is overridden, set to 'false' to disable
 * - string **filterHint** -- title attribute for filter cell
 * - string **$format** -- set to 'raw' to display HTML content, otherwise htmlspecialchars filter is applied. Also 'boolan' and 'checkbox' is supported.
 *
 * @package UMVC Simple Application Framework
 */
class Column extends Component
{
    /** @var Grid $grid -- the parent Grid */
    public Grid $grid;
    /** @var string|null $field -- the field-name of the model to display. May be null if a not-model column is displayed */
    public ?string $field = null;
    /** @var string|null $field -- the search field of the model to display. Default is the field ('.'-s are replaced with '_'-s) */
    public ?string $searchField = null;
    public string $width = '';
    /** @var string|null|false $label -- the label to display. Default is the model's label associated with the field. False to no label. */
    public string|null|false $label = null;
    /** @var string|bool|null $hint -- title (hint displayed at mouse hover) default is original label if label is overridden, set to false to disable */
    public string|null|bool $hint = null;
    /** @var callable $value -- function(Model $model):string -- computes the value of the column -- the default is the value of the field of the model */
    public $value;
    /** @var bool|array|string -- the column is filtered, or custom filter cell content */
    public string|array|bool $filter = true;
    /** @var bool|string -- the column is ordered, or custom order clauses separated by; and ASC is the first. Default is ordered. Set to 'false' to disable ordering. */
    public string|bool $order = true;
    /** @var bool|string $searchIcon -- search icon to display before search input in the search lane. Default is none. True = built-in magnifier icon. */
    public string|bool $searchIcon = false;
    /** @var bool|string $searchCancel -- display a search-cancel icon in the search lane. Set false to disable, true for the default icon or specify an HTML fragment to display */
    public bool|string $searchCancel = false;
    /** @var string $class -- custom class for value cell */
    public string $class = '';
    /** @var string $headerClass -- custom header class */
    public string $headerClass = '';
    /** @var string|Model $model -- the model name used in the table */
    public string|Model $model;
    /** @var string|null|bool -- display null value as. default is Grid's. Set false to disable (=empty string) */
    public string|bool|null $emptyValue = null;
    /** @var string|null $filterHint -- title attribute for filter cell */
    public ?string $filterHint = null;
    /** @var string $format -- none, raw, boolean, chckbox. */
    public string $format = '';
    public ?string $sortIcon = null;
    public ?string $sortAscIcon = null;
    public ?string $sortDescIcon = null;

    /**
     * @param Grid|null $grid
     * @param string $model
     * @param array $columnDef
     *
     * @return Column[]
     * @throws Exception
     */
    public static function createColumns(Grid|null $grid, string $model, array $columnDef): array
    {
        return array_map(function ($col) use ($grid, $model) {
            return static::createColumn($grid, $col, $model);
        }, $columnDef);
    }

    /**
     * @param Grid|null $grid
     * @param array $col
     * @param string|null $model
     *
     * @return Column
     * @throws Exception
     */
    private static function createColumn(?Grid $grid, array $col, string $model = null): Column
    {
        if (array_key_exists(0, $col)) {
            $col['field'] = array_shift($col);
        }
        if ($model) {
            $col['model'] = $model;
        }
        if ($grid) {
            $col['grid'] = $grid;
        }
        return new Column($col);
    }

    /**
     * @throws Exception
     */
    public function init(): void
    {
        $originalLabel = $this->model && $this->field ? $this->model::attributeLabel($this->field) : '';
        /** Auto-hint if a custom label is given  */
        if ($this->hint === null || $this->hint === true) {
            $this->hint = $this->label ? $originalLabel : '';
            if ($this->order) {
                $this->hint .= ($this->hint ? '. ' : '') . 'Click to order by this column.';
            }
        }
        /** Calculate label from model's attribute label */
        if ($this->label === false) {
            $this->label = '';
        } else {
            if ($this->label === null) {
                $this->label = $originalLabel;
            }
        }

        if (!$this->searchField) {
            $this->searchField = str_replace('.', '_', $this->field ?? '');
        }

        if ($this->emptyValue === null) {
            $this->emptyValue = $this->grid->emptyValue;
        }
        if ($this->emptyValue === false) {
            $this->emptyValue = '';
        }
        if($this->sortIcon === null) {
            $this->sortIcon = $this->grid->sortIcon;
        }
        if($this->sortAscIcon === null) {
            $this->sortAscIcon = $this->grid->sortAscIcon;
        }
        if($this->sortDescIcon === null) {
            $this->sortDescIcon = $this->grid->sortDescIcon;
        }
    }

    /**
     * Renders the search cell
     *
     * - boolean: display default filter input or none
     * - string: display in filter cell as it is
     * - array: display a selection
     *
     * @param array|BaseModel $search -- the search Model or an array with actual search values indexed by field names
     *
     * @return string
     * @throws Exception
     */
    public function renderSearch(BaseModel|array $search): string
    {
        $searchModel = 'search';
        $fieldName = $searchModel . '[' . $this->searchField . ']';
        $searchValue = ArrayHelper::getValue($search, $this->searchField);
        if (is_array($this->filter)) {
            $values = ['' => '&nbsp;'];
            foreach ($this->filter as $value => $label) {
                $values[$value] = $label;
            } // array_merge messes up indices
            $result = Html::select($values, ['name' => $fieldName, 'class' => 'grid-filter'], $searchValue);
        } else {
            if ($this->filter && $this->filter !== true) {
                $result = $this->filter;
            } else {
                // The default filter cell content is an input filed preceded with an optional icon.
                $result = $this->field && $this->filter
                    ? sprintf('<input name="%s" value="%s" class="grid-filter" />', $fieldName, $searchValue)
                    : '';
                if ($this->searchIcon) {
                    if ($this->searchIcon === true) {
                        $this->searchIcon = '<i class="fa fa-search" style="display: table-cell; width:2rem;"></i>';
                    }
                    $result = '<span class="search-icon"><a href="#" class="submit" title="Search!">' . $this->searchIcon . '</a></span><span>' . $result . '</span>';
                }
                if ($this->searchCancel) {
                    if ($this->searchCancel === true) {
                        $this->searchCancel = '<i class="fa fa-times" style="display: table-cell; width:2rem;"></i>';
                    }
                    $result = '<span>' . $result . '</span><span class="search-cancel"><a href="#" class="search-cancel" title="Cancel search">' . $this->searchCancel . '</a></span>';
                }
            }
        }
        $filterCellAttributes = [];
        if ($this->filterHint) {
            $filterCellAttributes['title'] = $this->filterHint;
        }
        return Html::tag('td', $result, $filterCellAttributes);
    }

    /**
     * @throws Exception
     */
    public function renderValue(BaseModel $model): string
    {
        $options = $this->class ? ['class' => $this->class] : [];
        if (is_callable($this->value)) {
            $content = call_user_func($this->value, $model);
        } else {
            if (is_string($this->value)) {
                $content = $model->{$this->value};
            } else {
                $content = $model->{$this->field};
            }
            $content = match($this->format) {
                'raw' => $content,
                'boolean' => $content===null ? $this->emptyValue : ($content ? 'Yes' : 'No'),
                'checkbox' => $content===null ? '' : ($content ? '<i class="far fa-check-square"></i>' : '<i class="far fa-square"></i>'),
                default => $content===null ? $this->emptyValue : htmlspecialchars($content),
            };
        }
        if (!is_scalar($content)) {
            throw new Exception("Got " . gettype($content) . " at $this->field of " . get_class($model));
        }
        return Html::tag('td', $content, $options);
    }

    /**
     * Renders the table header cell. Using priority, multiple orders can be applied.
     *
     * $orders are the $actualColumnOrder values by field names.
     * $actualColumnOrder is null, or actual order direction, with an optional priority postfix separated by;
     * Example: ['id'=>null, 'name'=>'ASC;1', ...]
     *
     * @param array|null $orders
     * @return string
     * @throws Exception
     */
    public function renderHeader(?array $orders): string
    {
        $class = $this->order ? 'header-ordered' : '';
        if ($this->headerClass) {
            $class .= ($class ? ' ' : '') . $this->headerClass;
        }
        $sorting = $this->renderSorting($orders);
        $span = Html::tag('span', $sorting . $this->label, ['title' => $this->hint]);
        $options = [
            'class' => $class
        ];
        if ($this->width) {
            $options['style'] = 'width:' . $this->width;
        }
        return Html::tag('th', $span, $options);
    }

    /**
     * Renders the actual sorting icon and inputs
     *
     * @param array $orders -- Example: ['id'=>'DESC;2', 'name'=>'ASC;1']
     * @return string
     */
    private function renderSorting(array $orders): string
    {
        if (!$this->order) {
            return '';
        }
        $actualColumnOrder = explode(';', ArrayHelper::getValue($orders, $this->field) ?? '');
        $actualColumnDir = $actualColumnOrder[0] ?? '';
        $actualColumnPriority = $actualColumnOrder[1] ?? 0;
        $orderSerial = $actualColumnDir && $actualColumnPriority ? '<u>' . (int)$actualColumnPriority . '</u>' : '';
        $c = $actualColumnDir ? ($actualColumnDir == 'ASC' ? $this->sortAscIcon : $this->sortDescIcon) : $this->sortIcon;
        $orderIcon = '<i class="fa ' . $c . '"></i>';
        $value = $actualColumnDir ? $actualColumnDir . ';' . $actualColumnPriority : '';
        $disabled = $value ? '' : ' disabled';
        $orderInput = '<input type="hidden" name="orders[' . $this->field . ']" value="' . $value . '"' . $disabled . '>';
        return $orderSerial . $orderIcon . $orderInput;
    }
}
