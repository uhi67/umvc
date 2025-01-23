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
<div class="form-group field-<?= $field->type ?> <?= $field->divClass ?><?= $hasErrors ?>" <?= $field->renderOptions(
    $field->divOptions
) ?>>
    <?php
    if ($field->type != 'submit'): ?>
        <label for="<?= $field->id ?>" <?= $field->labelClass(
        ) ?>><?= $field->label ?><?= $field->requiredMark ?></label>
    <?php
    endif; ?>
    <?= $field->renderInput(); ?>
    <div class="field-error"><?= $field->error ?></div>
</div>
