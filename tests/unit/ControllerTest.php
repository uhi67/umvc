<?php

class ControllerTest extends \Codeception\Test\Unit
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

// This test is skipped, because localizedViewName is already private
	/**
	 * @dataProvider provLocalizedViewName
	 * @return void
	 */
//    public function testLocalizedViewName($view, $locale, $localizedView)
//    {
//	    $this->assertEquals($localizedView, \uhi67\umvc\Controller::localizedViewName($view, $locale));
//    }
//	public function provLocalizedViewName() {
//		return [
//			['aaa', 'en', 'en/aaa'],
//			['bbb/aaa', 'en-GB', 'bbb/en-GB/aaa'],
//		];
//	}
}
