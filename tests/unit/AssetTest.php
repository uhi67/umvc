<?php

use uhi67\umvc\App;
use uhi67\umvc\Controller;
use uhi67\umvc\Asset;

class AssetTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    private App $app;
    private Controller $controller;
    
    protected function _before()
    {
        $this->app = App::$app;
        $this->controller = new Controller([
            'app' => $this->app,
            'action' => 'default',
        ]);
    }

    protected function _after()
    {
    }

    // tests
    public function testX()
    {
    }
}