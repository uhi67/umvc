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

	/**
	 * @dataProvider provLocalizedViewName
	 * @return void
	 */
    public function testLocalizedViewName($view, $locale, $localizedView)
    {
	    $this->assertEquals($localizedView, \uhi67\umvc\Controller::localizedViewName($view, $locale));
    }
	public function provLocalizedViewName() {
		return [
			['aaa', 'en', 'en/aaa'],
			['bbb/aaa', 'en-GB', 'bbb/en-GB/aaa'],
		];
	}
}
