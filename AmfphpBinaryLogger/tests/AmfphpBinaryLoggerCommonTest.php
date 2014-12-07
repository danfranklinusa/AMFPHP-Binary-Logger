<?php
/**
 * Created by JetBrains PhpStorm.
 * User: UFRAND3
 * Date: 6/21/13
 * Time: 8:00 PM
 * To change this template use File | Settings | File Templates.
 */

require_once 'common.php';

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'AmfphpBinaryLoggerCommon.php';

class AmfphpBinaryLoggerCommonTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AmfphpBinaryLoggerCommon
     */
    public $obj;

    public function setUp()
    {
        $this->obj = new AmfphpBinaryLoggerCommon();
    }

    public function testEscape()
    {
        $vexingString = AmfphpBinaryLoggerCommon::PREFIX . AmfphpBinaryLoggerCommon::PREFIX . "\n\t\x00 ID here\n" . AmfphpBinaryLoggerCommon::RS;
        $esc = $this->obj->escape($vexingString);
        $this->assertEquals("\x01\x82\x01\x82\x01\x81\t\x00 ID here\x01\x81\x01\x83", $esc);
        $empty = '';
        $this->assertEquals($empty, $this->obj->escape($empty));
    }

    public function testIdentity()
    {
        $vexingString = "\x01\x01\n\t ID here\n" . AmfphpBinaryLoggerCommon::RS;
        $this->assertEquals($vexingString, $this->obj->unescape($this->obj->escape($vexingString)));
    }

    public function testGetDefaultDirectory()
    {
        $this->assertTrue(is_dir($this->obj->getDefaultDirName()));
    }

    public function testGetDefaultFileNamePattern()
    {
        $this->assertEquals('amfphp-%Y%m%d.log', $this->obj->getDefaultFileNamePattern());
    }

    public function testGetTypeName()
    {
        $type = AmfphpBinaryLoggerCommon::REQUEST_TYPE;
        $this->assertEquals(
            AmfphpBinaryLoggerCommon::$typeNames[$type],
            AmfphpBinaryLoggerCommon::getTypeName($type)
        );
        $this->assertEquals('X??', AmfphpBinaryLoggerCommon::getTypeName('X'));
    }
}
