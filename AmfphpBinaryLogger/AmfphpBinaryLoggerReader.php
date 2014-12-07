<?php

/**
 * Class to read the binary log produced by AmfphpBinaryLogger
 * See AmfphpBinaryLoggerReader::show for a simple example of reading and printing a logfile.
 *
 * @version SVN: $Revision: 131898 $
 */

require_once 'Amfphp/ClassLoader.php';
require_once 'AmfphpBinaryLoggerCommon.php';
require_once 'AmfphpBinaryLoggerEntry.php';

/**
 * The reader class for binary logs created by AmfphpBinaryLogger.
 */
class AmfphpBinaryLoggerReader extends AmfphpBinaryLoggerCommon
{
    protected $fileName;
    protected $fileHandle;
    protected $lineNumber;
    protected $closeOnEOF = false;
    
    /**
     * Reads and returns the entries from the given log.
     * You may supply just the filename, in which case the constructor will open the file,
     * or you can supply the filename and file handle, in which case the constructor will use the given file handle
     * and use the filename only in error messages.  The latter is useful for pipelines.
     * 
     * @param string   $fileName - name of file to read
     * @param resource $fileHandle - file handle (optional)
     *
     * @throws AmfphpBinaryLoggerException
     */
    public function __construct($fileName, $fileHandle = null)
    {
        if (is_null($fileHandle)) {
            $fileHandle = fopen($fileName, 'rb');
            if ($fileHandle === false) {
                throw new AmfphpBinaryLoggerException("Cannot open '$fileName' for reading");
            }
            $this->closeOnEOF = true;
        }

        $this->fileName = $fileName;
        $this->fileHandle = $fileHandle;
        $this->lineNumber = 0;
    }
    
    /**
     * Read one entry from the log opened at constructor time.
     * If the log entry is corrupt, it returns an error entry;
     * call getError() on the return value to check.
     * 
     * @return AmfphpBinaryLoggerEntry or false on EOF
     * @throws AmfphpBinaryLoggerException if file is not in our format
     */
    public function readLogEntry()
    {
        $line = fgets($this->fileHandle);
        if ($line === false) {
            if ($this->closeOnEOF) {
                fclose($this->fileHandle);
                $this->fileHandle = null;
            }
            return false;
        }
        $lineNumber = ++$this->lineNumber;

        return $this->decodeLogLine($line, $this->fileName, $lineNumber);
    }

    /**
     * Decode one line from a logfile into an AmfphpBinaryLoggerReaderEntry
     *
     * @param string $line       - line from logfile
     * @param string $fileName   - filename for error reporting
     * @param int    $lineNumber - filename for error reporting
     *
     * @return AmfphpBinaryLoggerEntry
     * @throws AmfphpBinaryLoggerException
     */
    public function decodeLogLine($line, $fileName, $lineNumber)
    {
        $place = "$fileName, line $lineNumber";
        /**
         * The AMF data may be very long.  For this reason we have an RS just before it.  We extract the part
         * before the RS and work on it so we do not need to be concerned about memory overhead.
         * This also gives us a sanity check - is this line really from our logs?  If not throw an exception.
         */
        $start = strstr($line, self::RS, true);
        if ($start === false) {
            throw new AmfphpBinaryLoggerException(
                "$place: no 0x" . bin2hex(self::RS) . " char found."
            );
        }
        $startLength = strlen($start) + 1; // +1 for the RS
        /**
         * Take it apart.  This is another sanity check.
         */
        $unk = AmfphpBinaryLoggerCommon::DURATION_UNKNOWN;
        if (!preg_match(
            '/^([\d.]+) (\d+)(?: D=([\d.]+|' . $unk . '))?(?: #(\d+))? (\w+) (\d+) /', $start, $matches
        )) {
            throw new AmfphpBinaryLoggerException(
                "$place: invalid timestamp, version, duration, number, type, or clientIdentifier length: '$start'"
            );
        }

        $timestamp = $matches[1];
        $version = $matches[2];
        $duration = (isset($matches[3]) && strlen($matches[3]))? $matches[3] : $unk;
        $seq = (isset($matches[4]) && strlen($matches[4]))? $matches[4] : '?';
        $type = $matches[5];
        $clientIdentifierLength = $matches[6];

        $entry = new AmfphpBinaryLoggerEntry($type, $timestamp);
        $entry->fileName = $fileName;
        $entry->lineNumber = $lineNumber;
        $entry->version = $version;
        $entry->duration = $duration;
        $entry->requestNum = $seq;

        $start = substr($start, strlen($matches[0]));

        if ($version > AmfphpBinaryLoggerCommon::VERSION_NUMBER) {
            return $entry->setError("logfile version $version > " . AmfphpBinaryLoggerCommon::VERSION_NUMBER);
        }
        if (!isset(self::$typeNames[$type])) {
            return $entry->setError("unrecognized type '$type'");
        }

        $entry->clientIdentifier = $this->unescape(substr($start, 0, $clientIdentifierLength));
        $start = substr($start, $clientIdentifierLength);
        if ($start{0} != ' ') {
            return $entry->setError("clientIdentifier not followed by blank");
        }
        $start = trim($start);
        /**
         * At this point beforeData should contain just the length of the AMF data itself.
         */
        if (!is_numeric($start)) {
            return $entry->setError("Non-numeric AMF data length '$start'");
        }
        $dataLength = $start;
        $rawData = $this->unescape(substr($line, $startLength, $dataLength));
        $decoder = new Amfphp_Core_Amf_Deserializer;
        try {
            /**
             * Sometimes, on corrupted input, the deserializer fails by attempting to index beyond the end of an array.
             * This throws an E_NOTICE error if they're being reported.  Catch those errors.
             */
            set_error_handler(
                function($errno, $errstr, $errfile, $errline) {
                    throw new AmfphpBinaryLoggerException($errstr);
                },
                E_NOTICE
            );
            $packet = $decoder->deserialize(array(), array(), $rawData);
            restore_error_handler();
        } catch (Exception $exc) {
            return $entry->setError("AMF decoding error: " . $exc->getMessage());
        }
        if ($line[$startLength + $dataLength] == self::RS) {
            $escapedData = substr($line, $startLength + $dataLength + 1, -1);
            $rawData = $this->unescape($escapedData);
            try {
                set_error_handler(
                    function($errno, $errstr, $errfile, $errline) {
                        throw new AmfphpBinaryLoggerException($errstr);
                    },
                    E_NOTICE
                );
                $packet2 = $decoder->deserialize(array(), array(), $rawData);
                restore_error_handler();
            } catch (Exception $exc) {
                return $entry->setError("AMF decoding error: " . $exc->getMessage());
            }
            $packet = array('IN' => $packet, 'OUT' => $packet2);
            $dataLength += 1 + strlen($escapedData);
        }
        $entry->data = $packet;
        if ($line{$startLength + $dataLength} != "\n") {
            return $entry->setError("no newline after binary AMF data");
        }
        return $entry;
    }

    /**
     * Simple interface to read and print a logfile. Example usage:
     *   php -r 'require "AmfphpBinaryLoggerReader.php"; AmfphpBinaryLoggerReader::show("/tmp/amfphp-20130405.log");'
     * 
     * @param string $fileName
     */
    public static function show($fileName)
    {
        $reader = new AmfphpBinaryLoggerReader($fileName);
        while (($entry = $reader->readLogEntry())) {
            print $entry;
        }
    }
}
