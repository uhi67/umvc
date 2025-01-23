<?php
/** @noinspection PhpUnused */

namespace uhi67\umvc;

use Exception;

/**
 * Grid widget.
 * A sortable, filterable table-like view of a set of Models
 *
 * Use the widget function to render a grid-view.
 *
 * **Example**
 * ```
 *     Grid::widget($this->controller, [
 *         'modelClass' => $modelClass,
 *         'models' => $models,
 *         'columns' => [
 *            ['id', 'width'=>'6%', 'searchIcon'=>true],
 *            ['title', 'width'=>'22%'],
 *            ['discipline.name', 'width'=>'20%', ],
 *            ['label'=>'actions', 'width'=>'11%', 'filter'=>false, 'order'=>false, 'searchCancel'=>true, 'value'=>function($model) {
 *               return '<a href="/admin/course/update/'.$model->id.'" class="btn">Edit</a>';
 *            }],
 *         ],
 *         'search' => $search,
 *         'orders' => $orders,
 *         'page' => $page,
 *         'totalPages' => $totalPages,
 *     ])
 * ```
 *
 * ### The configuration elements of the Grid
 *
 * - modelClass: the class-name of the model displayed
 * - models: the actual set of models to display on this page
 * - columns: column definitions, see {@see \uhi67\umvc\Column}
 * - search: the displayed orders of the columns
 * - orders: the displayed orders of the columns
 * - page: actual page for the pagination. null to disable pagination
 * - totalPages: number of total pages for the pagination
 *
 * @package UMVC Simple Application Framework
 */
class Grid extends Component
{
    /** @var string|BaseModel $modelClass */
    public $modelClass;
    /** @var BaseModel[] $models -- the actual set of models to display on this page */
    public $models;
    /** @var Column[] $columns -- column definitions, see {@see \uhi67\umvc\Column} */
    public $columns;
    /** @var string[] $orders -- the displayed orders of the columns */
    public $orders;
    /** @var array|BaseModel $search -- the search model used in the second header row */
    public $search;
    /** @var int $page -- actual page for the pagination. null to disable pagination. */
    public $page;
    /** @var int $totalPages -- number of total pages for the pagination. */
    public $totalPages;
    /** @var int $totalRows -- number of total rows to display as info */
    public $totalRows;
    /** @var Controller $controller -- the current executed controller the Grid was called from */
    public $controller;
    /** @var string|null|bool -- display null value as. default is 'not set'. Set false to disable (=empty string) */
    public $emptyValue;

    /**
     * Creates and renders a Grid widget
     * @param Controller $controller -- the caller controller instance
     * @param array $options -- configuration array, see {@see Grid}
     * @return string -- the rendered result
     * @throws Exception
     */
    public static function widget($controller, $options = [])
    {
        $options['controller'] = $controller;
        $grid = new Grid($options);
        return $grid->render();
    }

    /**
     * @throws Exception
     */
    public function init()
    {
        if ($this->emptyValue === null) {
            $this->emptyValue = Html::tag('i', 'not set', ['class' => 'null']);
        }
        if ($this->emptyValue === false) {
            $this->emptyValue = '';
        }

        $this->columns = Column::createColumns($this, $this->modelClass, $this->columns);
    }

    /**
     * Renders the Grid
     *
     * @return string
     * @throws Exception
     */
    public function render()
    {
        return App::$app->renderPartial('_grid', [
            'grid' => $this,
            'columns' => $this->columns,
            'models' => $this->models,
            'orders' => $this->orders,
            'search' => $this->search,
            'page' => $this->page,
            'totalPages' => $this->totalPages,
            'totalRows' => $this->totalRows,
        ]);
    }

    /**
     * Renders the pagination buttons for the paginated view.
     *
     * If the number of pages less than 1, no buttons are displayed.
     * The button row contains:
     * - a [1] button for the first page (always, but may be the same as the current),
     * - an optional [...] to indicate skipped pages,
     * - _distance_ number (or less) of buttons before the current page,
     * - the current page button with active state (always),
     * - _distance_ number (or less) of buttons after the current page,
     * - an optional [...] to indicate skipped pages,
     * - a numbered button wih last page number (always, but may be the same as the current).
     *
     * If the number of pages are not enough to display all the above, some of them are skipped
     *
     * @param int $currentPage
     * @param int $totalPages
     * @param string $baseUrl
     * @param int $distance -- Number of buttons displayed before and after the current page
     * @return string
     * @throws Exception
     */
    public function paginationLinks($currentPage, $totalPages, $baseUrl = null, $distance = 4)
    {
        if ($totalPages <= 1) {
            return '';
        }
        $listItems = '';

        // The [1] button if not displayed with the group
        if ($currentPage > $distance + 1) {
            $listItems .= Html::tag(
                'li',
                Html::tag('a', 1, ['href' => $this->controller->app->createUrl([$baseUrl, 'page' => 1])])
            );
        }

        // The [...] button between the [1] and the first grouped page button
        if ($currentPage > $distance + 2) {
            $listItems .= Html::tag('li', Html::tag('a', '...'), ['class' => "disabled"]);
        }

        // Grouped numbered buttons from [current-distance] to [current+distance]
        $first = $currentPage > $distance + 1 ? $currentPage - $distance : 1;
        for ($i = $first; $i <= ($currentPage + $distance) && ($i <= $totalPages); $i++) {
            $options = [];
            if ($currentPage == $i) {
                $options['class'] = "active";
            }
            $listItems .= Html::tag(
                'li',
                Html::tag('a', $i, ['href' => $this->controller->app->createUrl([$baseUrl, 'page' => $i])]),
                $options
            );
        }

        // The [...] button between last grouped page button and the [Last] button
        if ($currentPage + $distance + 1 < $totalPages) {
            $listItems .= Html::tag('li', Html::tag('a', '...'), ['class' => "disabled"]);
        }

        // The [Last] button if not displayed within the group
        if ($currentPage + $distance < $totalPages) {
            $listItems .= Html::tag(
                'li',
                Html::tag(
                    'a',
                    $totalPages,
                    ['href' => $this->controller->app->createUrl([$baseUrl, 'page' => $totalPages])]
                )
            );
        }

        return Html::tag('ul', $listItems, ['class' => "pagination text-center"]);
    }
}
