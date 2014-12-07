<?php
/**
 * Define constants and methods used by both AmfphpBinaryLogger and AmfphpBinaryLoggerReader.
 *
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @version SVN: $Revision: 131898 $
 */

class AmfphpBinaryLoggerException extends Exception { }

class AmfphpBinaryLoggerCommon {

    const VERSION_NUMBER = 2;

    const RS = "\x1E";
    const PREFIX = "\x01";
    const ESCAPED_NEWLINE = "\x81";
    const ESCAPED_PREFIX = "\x82";
    const ESCAPED_RS = "\x83";

    const REQUEST_TYPE = 'RQT';
    const RESPONSE_TYPE = 'RPT';
    const TRANSACTION_TYPE = 'TRN'; /* Both request and response in one line */

    const DURATION_UNKNOWN = '-'; /* Time taken between request and response not known */

    public static $typeNames = array(
        self::REQUEST_TYPE => 'Request',
        self::RESPONSE_TYPE => 'Response',
        self::TRANSACTION_TYPE => 'TRN',
    );

    /**
     * Encode an arbitrary binary string so that it has no newlines or ASCII RS.
     *
     * @param string $v
     *
     * @return string
     */
    public function escape(&$v) {
        $pre = self::PREFIX;
        return str_replace(
            array($pre, "\n", self::RS),
            array($pre . self::ESCAPED_PREFIX, $pre . self::ESCAPED_NEWLINE, $pre . self::ESCAPED_RS),
            $v
        );
    }

    /**
     * Decode a binary string encoded with $this->escape.
     *
     * @param string $v
     *
     * @return string
     */
    public function unescape($v) {
        $pre = self::PREFIX;
        return str_replace(
            array($pre . self::ESCAPED_NEWLINE, $pre . self::ESCAPED_PREFIX, $pre . self::ESCAPED_RS),
            array("\n", $pre, self::RS),
            $v
        );
    }

    /**
     * @return string - default directory name
     */
    public function getDefaultDirName() {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string - default log filename pattern
     */
    public function getDefaultFileNamePattern() {
        return 'amfphp-%Y%m%d.log';
    }

    /**
     * @param string $type - type in logfile
     *
     * @return string typeName - human-readable type
     */
    public static function getTypeName($type)
    {
        if (!isset(self::$typeNames[$type])) {
            return $type . '??';
        }
        return self::$typeNames[$type];
    }
}
