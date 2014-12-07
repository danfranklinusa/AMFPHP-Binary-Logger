<?php

require_once 'common.php';

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AmfphpBinaryLogger.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AmfphpBinaryLoggerReader.php';

class AmfphpBinaryLoggerTest extends AmfphpBinaryLoggerTestUtils
{
    /**
     * @var AmfphpBinaryLogger
     */
    public $logger;
    public $fileName;

    public function testConstructorDefaults()
    {
        $o = new AmfphpBinaryLogger();
        $this->assertInstanceOf('AmfphpBinaryLogger', $o);
    }

    public function testConstructorSettings()
    {
        $tdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'subdir';
        if (!is_dir($tdir)) {
            mkdir($tdir, 0777, true);
        }

        $config = array(
            'dirName' => $tdir . DIRECTORY_SEPARATOR,
            'fileNamePattern' => '%Y%m%d.log',
            'clientIdentifier' => "\x01\x02\n\t ID here\n"
        );
        $logger = new AmfphpBinaryLogger($config);
        $this->assertEquals($config['dirName'], $logger->getDirName());
        $this->assertEquals($config['fileNamePattern'], $logger->getFileNamePattern());
        $this->assertEquals($config['clientIdentifier'], $logger->getClientIdentifier());
    }

    public function testConstructorBadDir()
    {
        $config = array('dirName' => '/no/such/directory/');
        try {
            $logger = new AmfphpBinaryLogger($config);
            $this->assertTrue(false, "AmfphpBinaryLogger failed to raise expected exception");
        } catch (AmfphpBinaryLoggerException $exc) {
            $this->assertEquals(
                "Log directory '/no/such/directory/' does not exist or is not a directory",
                $exc->getMessage()
            );
        }
    }

    public function cons($config = array())
    {
        $logger = new AmfphpBinaryLogger(array('fileNamePattern' => __CLASS__ . ".txt") + $config);
        $fileName = $logger->getDirName() . $logger->getFileNamePattern();
        if (file_exists($fileName)) {
            unlink($fileName);
        }
        $this->logger = $logger;
        $this->fileName = $fileName;
    }

    public function testLogData()
    {
        $this->cons();
        $logger = $this->logger;
        $fileName = $this->fileName;

        $logger->logData(AmfphpBinaryLogger::REQUEST_TYPE, 0, "\x00\x01\x02\x81\n\n\t Other Random Data");
        $logger->logData(AmfphpBinaryLogger::RESPONSE_TYPE, 0.01, '');
        $this->assertFileExists($fileName);
        $lines = file($fileName);
        $this->assertEquals(2, count($lines), "Wrong # of lines in logfile '$fileName'");
        $v = AmfphpBinaryLogger::VERSION_NUMBER;
        $this->assertRegexp(
            '/^[\d.]+ ' . $v . ' ' . 'D=0' . ' #0 ' . AmfphpBinaryLogger::REQUEST_TYPE . ' 0 .*Other Random Data$/',
            $lines[0]
        );
        $this->assertRegexp(
            '/^[\d.]+ ' . $v . ' ' . 'D=0.01' . ' #0 ' . AmfphpBinaryLogger::RESPONSE_TYPE . ' 0 /',
            $lines[1]
        );
    }

    public function testInterface()
    {
        $this->cons(array('clientIdentifier' => 'MyCookie'));
        $logger = $this->logger;
        $fileName = $this->fileName;

        foreach (array('First', 'Second') as $input) {
            $logger->filterSerializedRequest($this->serializeAMF($this->createAMF("$input Request")));
            usleep(25000);
            $logger->filterSerializedResponse($this->serializeAMF($this->createAMF("$input Response")));
        }
        $lines = file($fileName);
        $this->assertEquals(2, count($lines), "Wrong # of lines in logfile '$fileName'");

        $reader = new AmfphpBinaryLoggerReader($fileName);

        $entry = $reader->decodeLogLine($lines[0], $fileName, 1);
        $this->assertEquals($entry->requestNum, 1);
        $this->assertGreaterThanOrEqual(0.025, $entry->duration);
        $this->assertEquals('MyCookie', $entry->clientIdentifier);

        $entry = $reader->decodeLogLine($lines[1], $fileName, 2);
        $this->assertEquals($entry->requestNum, 2);
        $this->assertEquals('MyCookie', $entry->clientIdentifier);

    }

}
