<?php /** @noinspection PhpUnhandledExceptionInspection */

use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use uhi67\umvc\Html;

/** @var $content string */

$versionFile = '/app/version';
$versionStr = file_exists($versionFile) ? file_get_contents($versionFile) : '';
$versionMsg = 'UMVC Version ' . ($versionStr ?: 'failed to read');
?>

<!DOCTYPE html>
<html lang="<?= App::$app->locale ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="Author" content="Uherkovich Peter">
    <title>UMVC test</title>
	<link href="/bootstrap.css" rel="stylesheet">
	<link href="/site.css" rel="stylesheet">
</head>
<body>

<div class="wrap layout-main">
	<?= $this->renderPartial('widgets/navbar', [
		'brandLabel' => Html::tag('div',
			Html::tag('div',
				'UMVC test',
				['class'=>'brand-name']),
			['class'=>'brand-group']),
		'brandUrl' => '/',
		'options' => ['class' => 'navbar navbar-fixed-top app-top navbar-light navbar-expand-md'],
		'content' => $this->renderPartial('widgets/nav', [
			'options' => ['class' => 'navbar-nav navbar-right ms-md-auto'],
			'items' => [],
		]),
	]) ?>

	<div class="container">
        <?= $this->renderPartial('widgets/flashMessages', ['messages' => App::getFlashMessages(true)]) ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
	<div class="container">
		<div class="float-end"><?= $versionMsg ?></div>
		<strong>&copy; uhi67 <?= date('Y') ?></strong>
	</div>
</footer>

</body>
</html>
