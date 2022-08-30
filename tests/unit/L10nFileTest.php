<?php

use Codeception\Test\Unit;
use uhi67\umvc\App;
use uhi67\umvc\L10nFile;

class L10nFileTest extends Unit
{
    /**
     * @var UnitTester
     * @noinspection PhpMultipleClassDeclarationsInspection
     */
    protected $tester;

	/** @var App $app */
	public $app;

    protected function _before()
    {
	    App::$app->locale = 'hu-HU';
	    $this->app = App::$app;
	}

    protected function _after()
    {
    }

    // tests

	/** @noinspection PhpPossiblePolymorphicInvocationInspection */
	public function testL()
    {
	    $this->assertInstanceOf(L10nFile::class, App::$app->l10n);
	    $this->assertEquals(dirname(__DIR__).DIRECTORY_SEPARATOR.'_data/messages/cat1', App::$app->l10n->directory('cat1'));
	    $this->assertEquals(dirname(__DIR__).DIRECTORY_SEPARATOR.'_data/messages/app', App::$app->l10n->directory('app'));
	    $this->assertEquals(dirname(__DIR__,2).'/messages', App::$app->l10n->directory('umvc'));
	    $this->assertEquals('hu-HU', App::$app->locale);
	    $this->assertEquals('hu', App::$app->l10n->lang);
	    $this->assertEquals(dirname(__DIR__).DIRECTORY_SEPARATOR.'_data/messages', App::$app->l10n->dir);
	    $this->assertEquals('Fordítás', App::l('app', 'Translation', [], 'hu'));
	    $this->assertEquals('Fordítás', App::l('app', 'Translation'));
	    $this->assertEquals('any***', App::l('umvc/other', 'any'));
		$this->assertEquals('kötelező', App::l('umvc', 'is mandatory'));
	    $this->assertEquals('any other non-existing*', App::l('umvc', 'any other non-existing'));
	    $this->assertEquals('Lokalizáció', App::l('cat1', 'Localization'));
    }
}