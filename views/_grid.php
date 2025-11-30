<?php
/** @noinspection PhpUnhandledExceptionInspection */

use uhi67\umvc\BaseModel;
use uhi67\umvc\Column;
use uhi67\umvc\Grid;
use app\lib\App;

/** @var App $this */
/** @var Grid $grid -- the Grid instance called this view */
/** @var Column[] $columns */
/** @var BaseModel[] $models -- the actual set of models to display on this page */
/** @var array $search  -- the search values in the second header row as [fieldname=>value,...] */
/** @var string[] $orders -- the displayed orders of the columns */
/** @var $page -- */
/** @var $totalPages -- */
/** @var $totalRows -- */
?>

<form class="form form-inline" action="">
    <?= is_callable($grid->before) ? ($grid->before)($grid) : $grid->before ?>
    <input type="hidden" name="orders[default]" value="" />
    <?php if($totalPages>1): ?>
        <p>Showing <?= count($models) ?> records of total <?= $totalRows ?>, Page <?=$page ?> of total <?= $totalPages ?></p>
    <?php else: ?>
        <p>Found <?= count($models) ?> records</p>
    <?php endif; ?>
    <table class="table table-hover table-striped table-bordered table-condensed">
        <thead>

        <!-- Header row with column labels -->
        <tr>
            <?php foreach($columns as $column) echo $column->renderHeader($orders); ?>
        </tr>

        <!-- Search (filter) row -->
        <?php if($search!==false): ?>
            <tr class="search">
                <?php foreach($columns as $column) {
                    echo $column->renderSearch($search??[]);
                } ?>
            </tr>
        <?php endif; ?>
        </thead>

        <!-- Data rows -->
        <tbody>
        <?php foreach ($models as $model): ?>
            <tr>
                <?php foreach($columns as $column) {
                    echo $column->renderValue($model);
                } ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</form>

<?php if($page!==null): ?>
    <!-- Pagination -->
    <div class="text-center">
        <?= $grid->paginationLinks($page, $totalPages, $this->url) ?>
    </div>
    <!-- //Pagination -->
<?php endif; ?>
