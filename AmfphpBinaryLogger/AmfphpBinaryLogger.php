<?php

/**
 * Logs requests and responses in their serialized AMF form;
 * this form is more compact and efficient than trying to store PHP objects in text form.
 *
 * A separate class reads the log.
 *
 * Configuration parameters: <br/>
 * dirName - directory containing logfiles.  Defaults to "/tmp/". <br/>
 * fileNamePattern - strftime format string for log filename.  Defaults to "amfphp-%Y%m%d.log". <br/>
 * clientIdentifier - optional string (e.g. cookie) distinguishing this client from all others. Defaults to empty.
 * <br/>
 * 
 * @package AmfphpBinaryLogger
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @author Ariel Sommeria-klein
 * @version SVN: $Revision: 131296 $
 */

require_once 'AmfphpBinaryLoggerCommon.php';

/**
 * Define the logging filter plugin.
 */
class AmfphpBinaryLogger extends AmfphpBinaryLoggerCommon {
    private $dirName;
    private $fileNamePattern;
    private $clientIdentifier = '';
    private $pendingRequest = null;
    private $pendingRequestStart;
    private $requestCount = 0;

    /**
     * Constructor.
     * 
     * @param array $config optional key/value pairs in an associative array:
     *    dirName          - log file directory, ending in the directory separator, default '/var/tmp/amfphp/'
     *    fileNamePattern  - strftime format string for the filename, default 'amfphp-%Y%m%d.log'
     *    clientIdentifier - string identifying the client, e.g. Apache cookie.  Defaults to empty.
     *
     * @throws AmfphpBinaryLoggerException
     */
    public function __construct(array $config = array()) {
        if (!empty($config['dirName'])) {
            $this->dirName = $config['dirName'];
        } else {
            $this->dirName = $this->getDefaultDirName();
        }
        $dirName = $this->dirName;
        if (!is_dir($dirName)) {
            throw new AmfphpBinaryLoggerException(
                "Log directory '$dirName' does not exist or is not a directory"
            );
        }

        if (!empty($config['fileNamePattern'])) {
            $this->fileNamePattern = $config['fileNamePattern'];
        } else {
            $this->fileNamePattern = $this->getDefaultFileNamePattern();
        }
        if (!empty($config['clientIdentifier'])) {
            $this->clientIdentifier = $config['clientIdentifier'];
        }

        $filterManager = Amfphp_Core_FilterManager::getInstance();

        $filterManager->addFilter(
            Amfphp_Core_Gateway::FILTER_SERIALIZED_REQUEST, $this,
            'filterSerializedRequest'
        );
        $filterManager->addFilter(
            Amfphp_Core_Gateway::FILTER_SERIALIZED_RESPONSE, $this,
            'filterSerializedResponse'
        );
    }

    /**
     * Log the incoming packet before deserialization.
     *
     * @param string $rawData
     *
     * @return string
     */
    public function filterSerializedRequest($rawData) {
        /**
         * I don't know whether it's possible for this method to get called
         * twice in a row without a call to filterSerializedResponse.
         * Just in case, check for it and log a plain request if so.
         */
        if (!is_null($this->pendingRequest)) {
            $this->logData(self::REQUEST_TYPE, AmfphpBinaryLoggerCommon::DURATION_UNKNOWN, $rawData);
            $this->pendingRequest = null;
        }
        $this->pendingRequest = $rawData;
        $this->pendingRequestStart = microtime(true);
        $this->requestCount++;

        return $rawData;
    }

    /**
     * Log the outgoing packet after serialization.
     *
     * @param String $rawData
     *
     * @return string
     */
    public function filterSerializedResponse($rawData) {
        if (!is_null($this->pendingRequest)) {
            $duration = microtime(true) - $this->pendingRequestStart;
            $this->logData(self::TRANSACTION_TYPE, $duration, $this->pendingRequest, $rawData);
            $this->pendingRequest = null;
        } else {
            $this->logData(self::RESPONSE_TYPE, AmfphpBinaryLoggerCommon::DURATION_UNKNOWN, $rawData);
        }
        return $rawData;
    }

    /**
     * Write given data to log file, with timestamp, type, and client identifier (e.g. cookie).
     * Newlines in the binary data are escaped so that normal Linux utilities
     * like tail, sort, etc. can be used on the file.
     * \n is escaped as \x01\x81;
     * that means we need to escape \x01 as well, so it is converted to \x01\x82.
     *
     * @param string $type     - REQUEST_TYPE, RESPONSE_TYPE, or TRANSACTION_TYPE
     * @param float  $duration - time between request and response; may be DURATION_UNKNOWN
     * @param string $rawData  - AMF data
     * @param string $rawData2 - more AMF data
     *
     * @return void
     * @throws AmfphpBinaryLoggerException
     */
    public function logData($type, $duration, $rawData, $rawData2 = null) {
        $logFile = $this->dirName . strftime($this->fileNamePattern);
        $fh = fopen($logFile, 'a');
        if (!$fh) {
            throw new AmfphpBinaryLoggerException("Couldn't open log file '$logFile' for writing");
        }
        $message = $this->escape($rawData);
        if (!is_null($rawData2)) {
            $message2 = self::RS . $this->escape($rawData2);
        } else {
            $message2 = '';
        }

        $clientIdentifier = $this->escape($this->clientIdentifier);

        $start =  microtime(true) . ' ' . self::VERSION_NUMBER . ' ' . "D=$duration"
            . ' ' . "#{$this->requestCount}"
            . ' ' . $type
            . ' ' . strlen($clientIdentifier) . ' ' . $clientIdentifier;
        fwrite(
            $fh,
            $start . ' ' . strlen($message) . ' ' . self::RS . $message . $message2 . "\n"
        );
        fclose($fh);
    }

    public function getDirName() {
        return $this->dirName;
    }

    public function getFileNamePattern() {
        return $this->fileNamePattern;
    }

    public function getClientIdentifier() {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier($clientIdentifier) {
        $this->clientIdentifier = $clientIdentifier;
    }

}
