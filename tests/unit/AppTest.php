<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */

use Codeception\Test\Unit;
use uhi67\umvc\App;

class AppTest extends Unit {
    protected UnitTester $tester;
    public App $app;

    protected function _before() {
        App::$app->locale = 'hu-HU';
        $this->app = App::$app;
        $this->app->layout = 'layouts/rendertestlayout';
        $this->app->locale = 'hu-HU';
        require_once dirname(__DIR__) . '/_data/testapp/models/User.php';
    }

    protected function _after() {
    }

    /**
     * @dataProvider provLocalizedViewFile
     * @throws Exception
     */
    public function testLocalizedViewFile($expected, $view, $locale = null) {
        $viewPath = $this->app->basePath . '/views';
        $file = $this->app->localizedViewFile($view, $locale);
        $this->assertEquals($expected ? $viewPath . '/' . $expected : $expected, $file);
        if ($expected) $this->assertFileExists($file);
    }

    public static function provLocalizedViewFile() {
        return [
            // $expected, $view, $locale
            [null, 'invalid'],
            ['main/rendertest.php', 'main/rendertest'],
        ];
    }

    /**
     * @dataProvider provRender
     * @return void
     * @throws Exception -- only if the view path does not exist
     */
    public function testRender($expected, $view, $params = [], $layout = null, $layoutParams = null, $locale = null) {
        if (is_a($expected, Exception::class, true)) {
            $this->tester->expectThrowable(Throwable::class,
                function () use ($locale, $layoutParams, $layout, $params, $view, $expected) {
                    $this->assertEquals($expected, $this->app->render($view, $params??[], $layout, $layoutParams, $locale));
                });
        } else {
            $this->assertEquals($expected, $this->app->render($view, $params??[], $layout, $layoutParams, $locale));
        }
    }

    public static function provRender() {
        return [
            // $expected, $view, $params, $layout, $layoutParams, $locale
            // Invalid view name
            ["View file is not found for 'invalid'", 'invalid'],
            // Invalid layout name
            ["View file is not found for 'invalid'", 'main/rendertest', null, 'invalid'],
            // Render with missing 'param'
            [Exception::class, 'main/rendertest', null, null, null, false],
            // Normal render with the default layout and disabled locale
            ['<html><body><h2>Hello, buddy!</h2></body></html>', 'main/rendertest', ['name' => 'buddy'], null, null, false],
            // Normal render without layout with params and with the default locale
            ['<h2>Szia, haver!</h2>', 'main/rendertest', ['name' => 'haver'], false],
            // Normal render without layout and with explicit (undefined) locale
            ['<h2>Hello, kompis!</h2>', 'main/rendertest', ['name' => 'kompis'], false, null, 'se'],
            // Normal render without layout with params and with explicit locale
            ['<h2>Ciao, ragazzi!</h2>', 'main/rendertest', ['name' => 'ragazzi'], false, null, 'it'],
            // Normal render without layout with params and with explicit long locale
            ['<h2>Ciao, ragazzi!</h2>', 'main/rendertest', ['name' => 'ragazzi'], false, null, 'it-IT'],

            // View with partial render using explicit locale (main view untranslated, but partial view found localized)
            ["<h2>Hello, ragazzi!</h2>\r\n<p>ragazzi italiani</p>", 'main/rendertest2', ['name' => 'ragazzi'], false, null, 'it-IT'],
            // View with partial render using explicit locale (main view translated, but partial view not found)
            ["<h2>Szia, haver!</h2>\r\n<p>Welcome.</p>", 'main/rendertest3', ['name' => 'haver'], false, null, 'hu'],
        ];
    }
}