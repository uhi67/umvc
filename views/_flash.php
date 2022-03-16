<?php
/**
 * The default implementation of the flash message view.
 * Can be overridden by a same-named file in application's view directory.
 */
    /** @var $class */
    /** @var $text1 */
    /** @var $text2 */
?>

<div class="alert <?= $class ?> alert-dismissable" role="alert">
    <strong><?= $text1 ?></strong> <?= $text2 ?>
</div>
