<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace unit;

use uhi67\umvc\AppHelper;

class AppHelperTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests

	/**
	 * @dataProvider provWaitFor
	 * @group slow
	 * @return void
	 */
    public function testWaitFor($timeout, $interval, $length, $success, $attempts, $elapsed)
    {
		$start = time();
		$end = $start + $length;
	    $a = 0;
        // Call the function periodically until it returns true. Result is false if timeout occurred.
	    $result = AppHelper::waitFor(function() use($end, &$a) {
			$a++;
		    return time() >= $end;
	    }, $timeout, $interval);
        $e = time()-$start;

		$this->assertEquals($success, $result);
		$this->assertEqualsWithDelta($attempts, $a, 1.0);
		$this->assertEqualsWithDelta($elapsed, $e, 1.0);

    }
	public function provWaitFor() {
		return [
			// $timeout, $interval, $length, $success, $attempts, $elapsed
			[10, 1, 3, true, 4, 3],
			[3, 2, 4, false, 2, 3],
			[3, 10, 4, false, 1, 3],
			[0, 0, 4, false, 1, 1],
		];
	}

	/**
	 * @dataProvider provPathIsAbsolute
	 * @return void
	 */
	public function testPathIsAbsolute($path, $expected) {
		$this->assertEquals($expected, AppHelper::pathIsAbsolute($path));
	}
	public function provPathIsAbsolute() {
		return [
			['.', false],
			['', false],
			['../', false],
			['./any', false],
			['alma', false],
			['/', true],
			['/alma', true],
			['\\alma', true],
			['C:\\alma', true],
			['eee:\\alma', true],
			['_:\\alma', true],
			['D:alma', true],
			['http://alma', true],
			['mailto:info@umvc.test', true],
		];
	}
}