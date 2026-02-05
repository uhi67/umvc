<?php
/** @var $name */
/** @var \educalliance\umvc\App $this */
?>
<h2>Hello, <?= $name ?>!</h2>
<?= $this->renderPartial('main/_test2', ['name'=>$name]) ?>