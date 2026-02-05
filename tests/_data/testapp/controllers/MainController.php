<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

/** @noinspection PhpUnused */

namespace testapp\controllers;

use uhi67\umvc\Controller;

class MainController extends Controller
{
    public function actionDefault(): int|string
    {
        return $this->render('main/index');
    }
}
