<?php
/** @var string $brandLabel */
/** @var string $brandUrl */
/** @var array $options */
/** @var string $content */

use uhi67\umvc\Html;

if(!isset($options['id'])) $options['id'] = Html::nextId();
?>

<nav <?= Html::attributes($options) ?>>
	<div class="container">
		<a class="navbar-brand" href="<?= $brandUrl ?>"><?= $brandLabel ?></a>
		<button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#w0-collapse" aria-controls="w0-collapse" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
		<div id="<?= $options['id'] ?>-collapse" class="collapse navbar-collapse">
			<?= $content ?>
		</div>
	</div>
</nav>
