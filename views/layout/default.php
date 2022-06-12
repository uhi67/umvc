<?php
    /**
     * The default implementation of the main (default) layout file.
     * Can be overridden by a same-named file in application's view directory.
     *
     * @var $content -- the rendered content of a view
     */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
    <meta name="description" content="">
    <?= $this->controller->linkAssets(['css']) ?>
</head>

<body>
    <?= $content ?>
    <?= $this->controller->linkAssets(['js']) ?>
</body>
</html>
