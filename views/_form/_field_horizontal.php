<?php
    /**
     * This view renders a validated form field
     */

    /** @var Field $field */
    /** @var App $this */

use uhi67\umvc\App;
use uhi67\umvc\Field;

$hasErrors = $field->error ? 'has-error' : '';
?>
<div id="form-<?=$field->id?>" class="form-group row field-<?= $field->type ?> <?= $field->divClass ?><?= $hasErrors ?>" <?= $field->renderOptions($field->divOptions) ?>>
    <label for="<?= $field->id ?>" <?= $field->labelClass() ?>>
        <?= $field->type != 'submit' ? $field->label : '' ?>
        <?= $field->requiredMark ?>
    </label>
    <?php if($field->notice):?>
        <div <?= $field->noticeClass() ?>>
            <?= $field->notice; ?>
        </div>
    <?php endif; ?>
    <div class="<?= $field->form->wrapperClass ?>">
        <?= $field->renderInput(); ?>
        <div class="field-error"><?= $field->error ?></div>
        <div class="field-hint"><?= $field->hint ?></div>
    </div>
</div>
