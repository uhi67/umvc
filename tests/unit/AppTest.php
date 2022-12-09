<?php

use uhi67\umvc\App;

class AppTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
	/** @var App $app */
	public $app;
    
    protected function _before()
    {
	    App::$app->locale = 'hu-HU';
	    $this->app = App::$app;
	    require_once dirname(__DIR__).'/_data/testapp/models/User.php';
    }

    protected function _after()
    {
    }

	/**
	 * @dataProvider provRender
	 * @return void
	 * @throws Exception -- only if view path does not exist
	 */
	public function testRender($expected, $view, $params=null, $layout=null, $layoutParams=null, $locale=null)
    {
	    $this->assertEquals($expected, $this->app->render($view, $params, $layout, $layoutParams, $locale));
    }
    public function provRender()
    {
	    return [
			// $expected, $view, $params, $layout, $layoutParams, $locale
			[null, 'invalid'],
			['<h2>Hello, $name!</h2>', 'main/rendertest'],
	    ];
    }
}