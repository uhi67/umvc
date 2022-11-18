<?php
namespace unit;

use app\models\User;
use Codeception\Test\Unit;
use Exception;
use uhi67\umvc\App;
use uhi67\umvc\commands\MigrateController;
use UnitTester;

class MigrateTest extends Unit
{
    /**
     * @var UnitTester
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

    // tests

	/**
	 * @throws Exception
	 */
	public function testMigrate() {
	    $this->assertEquals( 200, $this->app->runController(MigrateController::class, [], [
			'confirm'=>'yes',
		    'verbose'=>3,
	    ]));
		$this->assertEqualsCanonicalizing(['course', 'migration', 'user'], $this->app->connection->getTables());
    }

	/**
	 * @throws Exception
	 */
	public function testReset() {
		$test1 = User::findUser('test1@umvc-test.test');
		if($test1) User::deleteAll(['uid'=>'test1@umvc-test.test']);

		$user = new User(['uid'=>'test1@umvc-test.test', 'name'=>'Test1']);
		$user->save();
		$test1 = User::findUser('test1@umvc-test.test');
		$this->assertNotNull($test1);
		$this->assertEquals('Test1', $test1->name);

		$action = 'reset';
		$this->assertEquals( 200, $this->app->runController(MigrateController::class, [$action], [
			'confirm'=>'yes',
			'verbose'=>3,
		]));
		$this->assertEqualsCanonicalizing(['course', 'migration', 'user'], $this->app->connection->getTables());
		$test1 = User::findUser('test1@umvc-test.test');
		$this->assertNull($test1);
    }
}
