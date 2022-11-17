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
    public function testMatchPattern() {
        $result = [];
        Asset::matchPattern(dirname(__DIR__,2).'/views', '', '*', function($fileName) use(&$result) {
            $result[] = $fileName;
        });
        $this->assertEquals(['_flash.php'], $result);

        $result = [];
        Asset::matchPattern(dirname(__DIR__,2).'/views', '', '*/_*', function($fileName) use(&$result) {
            $result[] = $fileName;
        });
        $this->assertEqualsCanonicalizing(['_form/_field.php', '_form/_field_horizontal.php', '_form/_notice.php', ], $result);
    }
}