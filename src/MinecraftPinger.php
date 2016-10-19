<?php
namespace MinecraftPinger;

use PhpUC\IO\Stream\ByteArrayInputStream;
use PhpUC\IO\Stream\ByteArrayOutputStream;
use PhpUC\IO\Stream\DataInputStream;
use PhpUC\IO\Stream\DataOutput;
use PhpUC\IO\Stream\DataOutputStream;
use PhpUC\Net\Socket\InetAddress;
use PhpUC\Net\Socket\Socket;
use PhpUC\Net\Socket\SocketException;

/**
 * PHP minecraft server pinger by Nathan Poirier (nathan818).
 */
class MinecraftPinger
{
    /**
     * @var string
     */
    private $hostname;

    /**
     * @var int
     */
    private $port;

    /**
     * A minecraft PC server pinger.
     *
     * @param string $hostname server address
     * @param int    $port     server port
     */
    public function __construct($hostname, $port = null)
    {
        $this->hostname = $hostname;
        $this->port = $port ?: 25565;
    }

    /**
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $connectTimeout       connection timeout in milliseconds
     * @param int $communicationTimeout communication timeout in milliseconds
     * @param int $protocolVersion      optional minecraft protocol version
     *
     * @return \stdClass the minecraft decoded json ping response
     */
    public function ping(
        $connectTimeout,
        $communicationTimeout,
        $protocolVersion = 1
    ) {
        $socket = Socket::createFromAddr(
            InetAddress::getByName($this->hostname),
            $this->port);
        try {
            $socket->connect($connectTimeout);
            $socket->setGlobalTimeout($communicationTimeout);
            $socketOut = new DataOutputStream($socket->getOutputStream());
            $socketIn = new DataInputStream($socket->getInputStream());

            $packet = new ByteArrayOutputStream();
            $packetOut = new DataOutputStream($packet);

            // Send Handshake
            ProtocolHelper::writeVarint($packetOut, 0x00); // Packet ID - VarInt
            ProtocolHelper::writeVarint($packetOut,
                $protocolVersion); // Protocol version - VarInt
            ProtocolHelper::writeString($packetOut,
                $this->hostname); // Server Address - String
            $packetOut->writeShort($this->port); // Port - Unsigned Short
            ProtocolHelper::writeVarint($packetOut, 1); // Next State - VarInt
            $this->writePacket($socketOut, $packet);

            // Send PingRequest
            ProtocolHelper::writeVarint($packetOut, 0x00); // Packet ID - VarInt
            $this->writePacket($socketOut, $packet);

            // Read PingResponse
            $packetLength = ProtocolHelper::readVarint($socketIn);
            if ($packetLength > 65536) {
                throw new MinecraftPingException('Too ping large response');
            }

            $packetIn = new DataInputStream(new ByteArrayInputStream(
                $socketIn->read($packetLength)));
            $packetId = ProtocolHelper::readVarint($packetIn);
            if ($packetId !== 0) {
                throw new MinecraftPingException('Invalid ping response packet ID');
            }

            $rawResponse = ProtocolHelper::readString($packetIn);
            $response = json_decode($rawResponse, false, 32);
            if ($response === false) {
                throw new MinecraftPingException('Invalid response json: ' .
                    json_last_error_msg());
            }

            return $response;
        } catch (SocketException $e) {
            throw new MinecraftPingException('Network error', null, $e);
        } finally {
            $socket->close();
        }
    }

    private function writePacket(DataOutput $os, ByteArrayOutputStream $packet)
    {
        $data = $packet->toByteArray();
        $packet->reset();
        ProtocolHelper::writeVarint($os, strlen($data));
        $os->writeBuf($data);
    }
}