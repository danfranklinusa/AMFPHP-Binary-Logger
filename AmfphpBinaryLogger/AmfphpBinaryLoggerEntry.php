<?php
/**
 *
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @version SVN: $Revision: 131898 $
 */
require_once 'AmfphpBinaryLoggerCommon.php';

/**
 * One entry from the AMF log.
 */
class AmfphpBinaryLoggerEntry
{
    private static $precision = 4;

    public $type;
    public $timestamp = 0;

    public $fileName = '';
    public $lineNumber = 0;
    public $version = null;
    public $duration = AmfphpBinaryLoggerCommon::DURATION_UNKNOWN;
    public $requestNum = 0;

    public $data = '';
    public $clientIdentifier = '';
    public $error = '';

    /**
     * Create an AmfphpBinaryLoggerEntry.
     *
     * @param string $type      - one of the types defined in AmfphpBinaryLoggerCommon
     * @param float  $timestamp - Timestamp with microseconds
     *
     * @throws AmfphpBinaryLoggerException
     */
    public function __construct($type, $timestamp)
    {
        $this->type = $type;
        if (!is_numeric($timestamp)) {
            throw new AmfphpBinaryLoggerException("Invalid timestamp '$timestamp'");
        }
        $this->timestamp = $timestamp;
    }

    /**
     * Return error, if any
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set error status
     *
     * @param string $errorMessage
     *
     * @return self for fluency
     */
    public function setError($errorMessage)
    {
        $this->error = $errorMessage;
        return $this;
    }

    /**
     * Return the timestamp, duration, entry type, and client identifier.
     * Broken out to make it easier to provide your own data display
     * or override the precision on fractional values.
     *
     * @param int $precision - (optional) floating-point precision to apply
     *
     * @return string
     */
    public function header($precision = null)
    {
        if ($precision === null) {
            $precision = self::$precision;
        }
        $t = $this->timestamp;
        $int = floor($t);
        $frac = round($t - $int, $precision);
        $frac = preg_replace('/^0*\./', '', $frac);
        $duration = $this->duration;
        $duration = ($duration === AmfphpBinaryLoggerCommon::DURATION_UNKNOWN? $duration : round($duration, $precision));
        $s = strftime("%Y-%m-%d %H:%M:%S.$frac", $int)
        . ' ' . $duration
        . ' ' . "#{$this->requestNum}"
        . ' ' . AmfphpBinaryLoggerCommon::getTypeName($this->type)
        . ' ' . $this->clientIdentifier;
        if ($this->getError()) {
            $s .= ' ' . $this->getError();
        }
        return $s;
    }

    public function __toString()
    {
        return $this->header() . ' ' . var_export($this->data, true) . "\n";
    }

    public static function setPrecision($ndig)
    {
        self::$precision = $ndig;
    }

    public static function getPrecision($ndig)
    {
        return self::$precision;
    }
}