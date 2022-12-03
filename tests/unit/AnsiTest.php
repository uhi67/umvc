<?php

use uhi67\umvc\Ansi;

class AnsiTest extends \Codeception\Test\Unit
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
    public function testColor()
    {
	    $this->assertEquals("\033[1;33m\033[44mbar", Ansi::color('bar', 'yellow', 'blue', false));
	    $this->assertEquals("\033[1;33mbar", Ansi::color('bar', 'yellow', null, false));
	    $this->assertEquals("\033[1;33m\033[44mbar\033[0m", Ansi::color('bar', 'yellow', 'blue'));
	    $this->assertEquals("\033[0;31mfoo\033[0m", Ansi::color('foo', 'red'));
    }
}
