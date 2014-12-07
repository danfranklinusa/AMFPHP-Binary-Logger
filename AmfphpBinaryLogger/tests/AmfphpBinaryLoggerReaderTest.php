<?php
/**
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @version SVN: $Revision: 131296 $
 */
include 'common.php';

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AmfphpBinaryLogger.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AmfphpBinaryLoggerReader.php';

class AmfphpBinaryLoggerReaderTest extends AmfphpBinaryLoggerTestUtils
{
    /**
     * @var AmfphpBinaryLogger
     */
    public $logger;
    public $clientId = 'A-Typical-Cookie';

    /**
     * @var string
     */
    public $fileName;

    public function setUp()
    {
        $config = array(
            'fileNamePattern' => __CLASS__ . ".txt",
            'clientIdentifier' => $this->clientId,
        );
        $logger = new AmfphpBinaryLogger($config);
        $fileName = $logger->getDirName() . $logger->getFileNamePattern();
        if (file_exists($fileName)) {
            unlink($fileName);
        }
        $this->logger = $logger;
        $this->fileName = $fileName;
    }

    public function tearDown()
    {
        if (file_exists($this->fileName)) {
            unlink($this->fileName);
        }
    }

    public function testReaderBasic()
    {
        $packet = $this->createAMF('This is a request');
        $response = $this->createAMF('This is a response');
        $this->logger->logData(
            AmfphpBinaryLogger::TRANSACTION_TYPE,
            1.1,
            $this->serializeAMF($packet),
            $this->serializeAMF($response)
        );
        $reader = new AmfphpBinaryLoggerReader($this->fileName);
        $entry = $reader->readLogEntry();
        $this->assertLessThanOrEqual(microtime(true), $entry->timestamp, "Timestamp problem");
        $this->assertEquals(AmfphpBinaryLoggerCommon::TRANSACTION_TYPE, $entry->type);
        $this->assertEquals($this->clientId, $entry->clientIdentifier);
        $this->assertEquals(array('IN' => $packet, 'OUT' => $response), $entry->data);
        $this->assertEquals(AmfphpBinaryLoggerCommon::VERSION_NUMBER, $entry->version);
        $trnName = AmfphpBinaryLoggerCommon::getTypeName(AmfphpBinaryLoggerCommon::TRANSACTION_TYPE);
        $this->assertRegExp(
            '@^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d(\.\d+)? [\d.]+ #0 ' . $trnName
            . ' ' . $this->clientId . '$@',
            $entry->header()
        );
        $this->assertEquals(false, $reader->readLogEntry());
    }

    public function testReaderVexing()
    {
        $packet = $this->createAMF(array("\x01\n\n\x00",'key' => 'value'));
        $this->logger->setClientIdentifier("Random\nString");
        $this->logger->logData(AmfphpBinaryLogger::RESPONSE_TYPE, 0.25, $this->serializeAMF($packet));

        $this->assertEquals(1, count(file($this->fileName))); /* Make sure it is only one line */
        $reader = new AmfphpBinaryLoggerReader($this->fileName);
        $entry = $reader->readLogEntry();
        $this->assertEquals(AmfphpBinaryLoggerCommon::RESPONSE_TYPE, $entry->type);
        $this->assertEquals("Random\nString", $entry->clientIdentifier);
        $this->assertEquals($packet, $entry->data);
    }

    public function testMultiLine()
    {
        $this->logger->setClientIdentifier("Random\nString");

        $packets[] = $this->createAMF(array("\x01\n\n\x00",'key' => 'value'));
        $this->logger->logData(AmfphpBinaryLogger::REQUEST_TYPE, 0.125, $this->serializeAMF($packets[0]));

        $packets[] = $this->createAMF((object)array('key' => 'value'));
        $this->logger->logData(AmfphpBinaryLogger::RESPONSE_TYPE, 0.5, $this->serializeAMF($packets[1]));

        $this->assertEquals(2, count(file($this->fileName)));

        $reader = new AmfphpBinaryLoggerReader($this->fileName);

        $entry = $reader->readLogEntry();
        $this->assertEquals(AmfphpBinaryLoggerCommon::REQUEST_TYPE, $entry->type);
        $this->assertEquals("Random\nString", $entry->clientIdentifier);
        $this->assertEquals($packets[0], $entry->data);

        $entry = $reader->readLogEntry();
        $this->assertEquals(AmfphpBinaryLoggerCommon::RESPONSE_TYPE, $entry->type);
        $this->assertEquals("Random\nString", $entry->clientIdentifier);
        $this->assertEquals($packets[1], $entry->data);
    }

    public function testFileHandle()
    {
        /**
         * To verify that the reader is really using the file handle, copy the file and delete the original.
         */
        $packet = $this->createAMF('This is a test');
        $this->logger->logData(AmfphpBinaryLogger::REQUEST_TYPE, 0.75, $this->serializeAMF($packet));
        $copyName = $this->fileName . ".copy";
        copy($this->fileName, $copyName);
        unlink($this->fileName);

        $fileHandle = fopen($copyName, 'rb');

        $reader = new AmfphpBinaryLoggerReader($this->fileName, $fileHandle);

        $entry = $reader->readLogEntry();
        $this->assertEquals($packet, $entry->data);

        $entry = $reader->readLogEntry();
        $this->assertFalse($entry);
        $this->assertTrue(feof($fileHandle));
    }

}
