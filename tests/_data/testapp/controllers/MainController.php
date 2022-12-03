<?php /** @noinspection PhpUnused */

namespace testapp\controllers;

use uhi67\umvc\Controller;

class MainController extends Controller {
    public function actionDefault() {
        echo $this->render('main/index');
    }
}
