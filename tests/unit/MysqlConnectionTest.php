<?php

use Codeception\Test\Unit;
use uhi67\umvc\App;
use uhi67\umvc\commands\MigrateController;
use uhi67\umvc\Connection;

class MysqlConnectionTest extends Unit
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
    }

	/**
	 * @throws Exception
	 */
	public function testConnect()
    {
		$db = $this->app->connection;
		$this->assertInstanceOf(Connection::class, $db);
	    $this->assertEquals('umvc-test', $db->getSchemaName());
	    $this->assertEquals(['course', 'migration', 'user'], array_keys($db->schemaMetadata));
	    $this->assertEquals(['course', 'migration', 'user'], $db->getTables());
	    $this->assertEquals(['course.id', 'user.id'], $db->getSequences());
	    $this->assertEquals(['umvc-test.course.fk_course_teacher', 'umvc-test.course.fk_course_created', 'umvc-test.course.fk_course_updated'], array_keys($db->foreignKeys));
	    $this->assertEquals([], $db->getForeignKeys('user'));
	    $this->assertEquals(['umvc-test.course.fk_course_teacher', 'umvc-test.course.fk_course_created', 'umvc-test.course.fk_course_updated'], array_keys($db->getReferrerKeys('user')));
	    $this->assertEquals([], $db->getRoutines());
	    $this->assertEquals([], $db->getTriggers());
    }
}
