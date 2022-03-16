<?php


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class UnitTester extends \Codeception\Actor
{
    use _generated\UnitTesterActions;

   /**
    * Define custom actions here
    * accessable as $this->tester->function
    */

    /**
     * Asserts the diercory does not contain files
     *
     * (May contain other directories)
     *
     * @param $dirName
     * @return void
     */
    public function assertDirectoryIsEmpty($dirName, $subdirs=false)
    {
        $this->assertDirectoryExists($dirName, 'Failed asserting thet directory is empty. Directory does not exist.');
        $d = dir($dirName);
        while($entry = $d->read()) {
            if(!$subdirs && filetype($dirName.'/'.$entry)!='file') continue;
            if($subdirs && in_array($entry, ['.', '..'])) continue;
            $this->assertFalse($entry, 'Failed asserting thet directory is empty. Contains file `'.$entry.'`');
        }
        $d->close();
    }
}
