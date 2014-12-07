<?php
/**
 * @author Dan Franklin <dan.franklin@pearson.com>
 * @version SVN: $Revision: 131297 $
 */

class AmfphpBinaryLoggerTestUtils extends PHPUnit_Framework_TestCase
{
    /**
     * Mock up some data the way it would appear in the AMFPHP library.
     *
     * @param mixed $data         - input data to be serialized as AMF
     * @param string $targetUri   - Target URI (optional)
     * @param string $responseUri - Response URI (optional)
     *
     * @return Amfphp_Core_Amf_Packet
     */
    public function createAMF($data, $targetUri = '/in', $responseUri = '/out')
    {
        $packet = new Amfphp_Core_Amf_Packet();
        $packet->messages[] = new Amfphp_Core_Amf_Message($targetUri, $responseUri, $data);
        return $packet;
    }

    /**
     * Serialize an AMF packet.
     *
     * @param Amfphp_Core_Amf_Packet $packet
     *
     * @return String
     */
    public function serializeAMF(Amfphp_Core_Amf_Packet $packet)
    {
        $encoder = new Amfphp_Core_Amf_Serializer();
        $encData = $encoder->serialize($packet);
        return $encData;
    }
}