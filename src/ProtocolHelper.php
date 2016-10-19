<?php
namespace MinecraftPinger;

use PhpUC\IO\Stream\DataInput;
use PhpUC\IO\Stream\DataOutput;

/**
 * Minecraft protocol helper.
 *
 * @link http://wiki.vg/Protocol
 */
class ProtocolHelper
{
    public static function writeVarint(DataOutput $os, $value)
    {
        // < 128 was stored on single byte
        if ($value < 128) {
            $os->writeByte($value);
        } else {
            $bytes = [];
            while ($value > 0) {
                $bytes[] = 0x80 | ($value & 0x7F);
                $value >>= 7;
            }
            $bytes[count($bytes) - 1] &= 0x7F;

            foreach ($bytes as $byte) {
                $os->writeByte($byte);
            }
        }
    }

    public static function readVarint(DataInput $is)
    {
        $result = 0;
        $shift = 0;
        do {
            $byte = $is->readUnsignedByte();
            $result |= ($byte & 0x7f) << $shift * 7;
            if (++$shift > 5) {
                throw new \InvalidArgumentException('VarInt is too big');
            }
        } while ($byte > 0x7f);

        return $result;
    }

    public static function writeString(DataOutput $os, $str)
    {
        $bytesLen = strlen($str);
        self::writeVarint($os, $bytesLen);
        $os->writeBuf($str);
    }

    public static function readString(DataInput $is, $sizeLimit = null)
    {
        $bytesLen = self::readVarint($is);
        if ($sizeLimit !== null && $bytesLen > $sizeLimit) {
            throw new \RuntimeException('String response is too large!');
        }

        return $is->readBuf($bytesLen);
    }
}