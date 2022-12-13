<?php
/** @var $name */
/** @var \uhi67\umvc\App $this */
?>
<h2>Hello, <?= $name ?>!</h2>
<?= $this->renderPartial('main/_test2', ['name'=>$name]) ?>