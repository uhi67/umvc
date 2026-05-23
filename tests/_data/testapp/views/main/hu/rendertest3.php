<?php
/** @var $name */
/** @var \educalliance\umvc\App $this */
?>
<h2>Szia, <?= $name ?>!</h2>
<?= $this->renderPartial('main/_test3', ['name'=>$name]) ?>