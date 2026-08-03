<?php
// +----------------------------------------------------------------------
// | 豆包端到端实时语音二进制协议
// +----------------------------------------------------------------------

namespace app\common\services\aiOrder;

class DoubaoProtocol
{
    public const CLIENT_FULL_REQUEST = 0b0001;
    public const CLIENT_AUDIO_ONLY_REQUEST = 0b0010;
    public const SERVER_FULL_RESPONSE = 0b1001;
    public const SERVER_ACK = 0b1011;
    public const SERVER_ERROR_RESPONSE = 0b1111;

    public const MSG_WITH_EVENT = 0b0100;
    public const NO_SERIALIZATION = 0b0000;
    public const JSON = 0b0001;
    public const GZIP = 0b0001;
    public const NO_COMPRESSION = 0b0000;

    public static function header(
        int $messageType = self::CLIENT_FULL_REQUEST,
        int $flags = self::MSG_WITH_EVENT,
        int $serial = self::JSON,
        int $compress = self::GZIP
    ): string {
        return chr((0b0001 << 4) | 0b0001)
            . chr(($messageType << 4) | $flags)
            . chr(($serial << 4) | $compress)
            . chr(0x00);
    }

    public static function packEvent(int $event, string $sessionId, $payload, bool $audioOnly = false): string
    {
        $isAudio = $audioOnly;
        $header = self::header(
            $isAudio ? self::CLIENT_AUDIO_ONLY_REQUEST : self::CLIENT_FULL_REQUEST,
            self::MSG_WITH_EVENT,
            $isAudio ? self::NO_SERIALIZATION : self::JSON,
            self::GZIP
        );
        if (is_array($payload) || is_object($payload)) {
            $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $raw = (string)$payload;
        }
        $zip = gzencode($raw);
        $out = $header;
        $out .= pack('N', $event);
        if ($sessionId !== '') {
            $out .= pack('N', strlen($sessionId));
            $out .= $sessionId;
        }
        $out .= pack('N', strlen($zip));
        $out .= $zip;
        return $out;
    }

    public static function packAudio(string $sessionId, string $pcm): string
    {
        $header = self::header(
            self::CLIENT_AUDIO_ONLY_REQUEST,
            self::MSG_WITH_EVENT,
            self::NO_SERIALIZATION,
            self::GZIP
        );
        $zip = gzencode($pcm);
        $out = $header;
        $out .= pack('N', 200); // TaskRequest
        $out .= pack('N', strlen($sessionId));
        $out .= $sessionId;
        $out .= pack('N', strlen($zip));
        $out .= $zip;
        return $out;
    }

    public static function parse(string $bin): array
    {
        if (strlen($bin) < 4) {
            return [];
        }
        $b0 = ord($bin[0]);
        $b1 = ord($bin[1]);
        $b2 = ord($bin[2]);
        $headerSize = ($b0 & 0x0f) * 4;
        $messageType = ($b1 >> 4) & 0x0f;
        $flags = $b1 & 0x0f;
        $serial = ($b2 >> 4) & 0x0f;
        $compress = $b2 & 0x0f;
        $payload = substr($bin, $headerSize);
        $result = [
            'message_type' => $messageType,
            'event' => 0,
            'session_id' => '',
            'payload_msg' => null,
            'payload_bytes' => '',
        ];
        $start = 0;
        if ($messageType === self::SERVER_FULL_RESPONSE || $messageType === self::SERVER_ACK) {
            if ($flags & 0b0011) {
                $start += 4; // seq
            }
            if ($flags & self::MSG_WITH_EVENT) {
                $result['event'] = unpack('N', substr($payload, $start, 4))[1];
                $start += 4;
            }
            $payload = substr($payload, $start);
            if (strlen($payload) < 4) {
                return $result;
            }
            $sidLen = unpack('N', substr($payload, 0, 4))[1];
            // signed in python but usually positive
            if ($sidLen > 0 && $sidLen < 1024 && strlen($payload) >= 4 + $sidLen + 4) {
                $result['session_id'] = substr($payload, 4, $sidLen);
                $payload = substr($payload, 4 + $sidLen);
            }
            if (strlen($payload) < 4) {
                return $result;
            }
            $size = unpack('N', substr($payload, 0, 4))[1];
            $msg = substr($payload, 4, $size);
            if ($compress === self::GZIP && $msg !== '') {
                $ungzip = @gzdecode($msg);
                if ($ungzip !== false) {
                    $msg = $ungzip;
                }
            }
            if ($serial === self::JSON) {
                $result['payload_msg'] = json_decode($msg, true);
            } else {
                $result['payload_bytes'] = $msg;
                $result['payload_msg'] = null;
            }
        } elseif ($messageType === self::SERVER_ERROR_RESPONSE) {
            $result['code'] = unpack('N', substr($payload, 0, 4))[1] ?? 0;
            $size = unpack('N', substr($payload, 4, 4))[1] ?? 0;
            $msg = substr($payload, 8, $size);
            if ($compress === self::GZIP) {
                $ungzip = @gzdecode($msg);
                if ($ungzip !== false) {
                    $msg = $ungzip;
                }
            }
            $result['payload_msg'] = $serial === self::JSON ? json_decode($msg, true) : $msg;
        }
        return $result;
    }

    /** PCM s16le → 可播放的 WAV */
    public static function pcmToWav(string $pcm, int $sampleRate = 24000, int $channels = 1, int $bits = 16): string
    {
        $dataLen = strlen($pcm);
        $byteRate = (int)($sampleRate * $channels * $bits / 8);
        $blockAlign = (int)($channels * $bits / 8);
        $header = 'RIFF' . pack('V', 36 + $dataLen) . 'WAVEfmt ' . pack('V', 16)
            . pack('v', 1) . pack('v', $channels) . pack('V', $sampleRate)
            . pack('V', $byteRate) . pack('v', $blockAlign) . pack('v', $bits)
            . 'data' . pack('V', $dataLen);
        return $header . $pcm;
    }
}
