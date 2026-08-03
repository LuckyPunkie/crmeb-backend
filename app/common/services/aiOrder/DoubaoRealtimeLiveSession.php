<?php
// +----------------------------------------------------------------------
// | 豆包端到端实时语音 - 长连接全双工（服务端 VAD）
// +----------------------------------------------------------------------

namespace app\common\services\aiOrder;

use think\facade\Log;

/**
 * 保持一条豆包 WebSocket：持续喂麦克风 PCM，服务端 VAD 判停后自动回复。
 * 同一会话内用 Channel 串行收发，避免多协程同时操作 Client。
 */
class DoubaoRealtimeLiveSession
{
    /** @var \Swoole\Coroutine\Http\Client|null */
    protected $client;

    protected string $sessionId = '';

    protected string $dialogId = '';

    protected bool $closed = false;

    /** @var \Swoole\Coroutine\Channel|null */
    protected $pcmCh;

    /** @var callable|null */
    protected $onEvent;

    protected string $pcmCarry = '';

    protected string $ttsPcm = '';

    protected array $aiSentences = [];

    protected string $asrText = '';

    protected int $usageTokens = 0;

    public function dialogId(): string
    {
        return $this->dialogId;
    }

    public function usageTokens(): int
    {
        return $this->usageTokens;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @param callable $onEvent fn(array $evt): void  evt.type = asr|ai_text|tts|status|error|hello_done
     */
    public function start(string $sessionId, string $systemRole, string $helloText, callable $onEvent): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('需要 Swoole 扩展');
        }
        $appId = (string)config('ai_order.doubao_app_id');
        $token = (string)config('ai_order.doubao_access_token');
        if ($appId === '' || $token === '') {
            throw new \RuntimeException('豆包密钥未配置');
        }

        $this->sessionId = $sessionId !== '' ? $sessionId : substr(md5(uniqid('ai', true)), 0, 32);
        $this->onEvent = $onEvent;
        $this->pcmCh = new \Swoole\Coroutine\Channel(200);
        $this->closed = false;

        $client = new \Swoole\Coroutine\Http\Client(DoubaoRealtimeClient::WS_HOST, 443, true);
        $client->set([
            'timeout' => 120,
            'websocket_mask' => true,
        ]);
        $client->setHeaders([
            'X-Api-App-ID' => $appId,
            'X-Api-Access-Key' => $token,
            'X-Api-Resource-Id' => 'volc.speech.dialog',
            'X-Api-App-Key' => 'PlgvMymc7f3tQnJ6',
            'X-Api-Connect-Id' => $this->uuid(),
        ]);
        if (!$client->upgrade(DoubaoRealtimeClient::WS_PATH)) {
            throw new \RuntimeException('豆包 WebSocket 升级失败: ' . $client->errMsg);
        }
        $this->client = $client;

        $client->push(DoubaoProtocol::packEvent(1, '', new \stdClass()), WEBSOCKET_OPCODE_BINARY);
        $this->recvUntil([50], 5);

        // 不传 input_mod = 默认麦克风 + 服务端 VAD（真正实时通话）
        $startSession = [
            'tts' => [
                'audio_config' => [
                    'format' => 'pcm_s16le',
                    'sample_rate' => 24000,
                    'channel' => 1,
                ],
                'speaker' => 'zh_female_vv_jupiter_bigtts',
            ],
            'asr' => [
                'audio_info' => [
                    'format' => 'pcm',
                    'sample_rate' => 16000,
                    'channel' => 1,
                ],
                'extra' => [
                    'end_smooth_window_ms' => 800,
                ],
            ],
            'dialog' => [
                'bot_name' => '点餐服务员',
                'system_role' => $systemRole,
                'speaking_style' => '热情、简洁',
                'dialog_id' => '',
                'extra' => new \stdClass(),
            ],
            'extra' => [
                'model' => 'O',
            ],
        ];
        $started = $this->recvUntil(
            [150],
            8,
            DoubaoProtocol::packEvent(100, $this->sessionId, $startSession)
        );
        if (is_array($started['payload_msg'] ?? null) && !empty($started['payload_msg']['dialog_id'])) {
            $this->dialogId = (string)$started['payload_msg']['dialog_id'];
        }

        if ($helloText !== '') {
            $client->push(
                DoubaoProtocol::packEvent(300, $this->sessionId, ['content' => $helloText]),
                WEBSOCKET_OPCODE_BINARY
            );
        }

        $this->emit(['type' => 'status', 'text' => 'live_started', 'dialog_id' => $this->dialogId]);

        // 泵：同时从豆包收事件、从 Channel 取 PCM 上送
        go(function () {
            $this->pump();
        });
    }

    public function feedPcm(string $pcmBinary): void
    {
        if ($this->closed || !$this->pcmCh) {
            return;
        }
        if ($pcmBinary === '') {
            return;
        }
        // 满了就丢最旧的，保证实时
        if ($this->pcmCh->isFull()) {
            $this->pcmCh->pop(0.001);
        }
        $this->pcmCh->push($pcmBinary, 0.01);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        try {
            if ($this->pcmCh) {
                $this->pcmCh->push(['__close' => 1], 0.01);
            }
        } catch (\Throwable $e) {
        }
        try {
            if ($this->client) {
                $this->client->push(DoubaoProtocol::packEvent(102, $this->sessionId, new \stdClass()), WEBSOCKET_OPCODE_BINARY);
                $this->client->push(DoubaoProtocol::packEvent(2, '', new \stdClass()), WEBSOCKET_OPCODE_BINARY);
                $this->client->close();
            }
        } catch (\Throwable $e) {
        }
        $this->client = null;
    }

    protected function pump(): void
    {
        $idleSilence = str_repeat("\0", 640); // 20ms 静音，保持链路活跃
        $lastFeedAt = microtime(true);
        while (!$this->closed && $this->client) {
            try {
                // 1) 上送麦克风
                $item = $this->pcmCh ? $this->pcmCh->pop(0.01) : false;
                if (is_array($item) && !empty($item['__close'])) {
                    break;
                }
                if (is_string($item) && $item !== '') {
                    $this->pushPcmChunks($item);
                    $lastFeedAt = microtime(true);
                } elseif ((microtime(true) - $lastFeedAt) > 0.4) {
                    // 短暂无麦：补静音，避免服务端判超时（keep_alive 场景同类）
                    $this->pushPcmChunks($idleSilence);
                    $lastFeedAt = microtime(true);
                }

                // 2) 收豆包事件
                $frame = $this->client->recv(0.05);
                if ($frame === false || $frame === '') {
                    continue;
                }
                $data = is_object($frame) ? ($frame->data ?? '') : $frame;
                if ($data === '' || $data === true) {
                    continue;
                }
                $this->handleFrame((string)$data);
            } catch (\Throwable $e) {
                Log::warning('Doubao live pump: ' . $e->getMessage());
                $this->emit(['type' => 'error', 'text' => $e->getMessage()]);
                break;
            }
        }
        $this->close();
    }

    protected function pushPcmChunks(string $pcm): void
    {
        if (!$this->client || $this->closed) {
            return;
        }
        $this->pcmCarry .= $pcm;
        $chunk = 640;
        while (strlen($this->pcmCarry) >= $chunk) {
            $part = substr($this->pcmCarry, 0, $chunk);
            $this->pcmCarry = substr($this->pcmCarry, $chunk);
            $this->client->push(DoubaoProtocol::packAudio($this->sessionId, $part), WEBSOCKET_OPCODE_BINARY);
        }
    }

    protected function handleFrame(string $data): void
    {
        $parsed = DoubaoProtocol::parse($data);
        $event = (int)($parsed['event'] ?? 0);
        $msg = $parsed['payload_msg'] ?? null;

        if ($event === 51 || $event === 153) {
            $err = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : 'session error';
            $this->emit(['type' => 'error', 'text' => $err]);
            return;
        }
        if ($event === 150 && is_array($msg) && !empty($msg['dialog_id'])) {
            $this->dialogId = (string)$msg['dialog_id'];
        }
        if ($event === 451 && is_array($msg)) {
            $results = $msg['results'] ?? [];
            $text = '';
            if ($results) {
                $last = $results[count($results) - 1];
                $text = (string)($last['text'] ?? '');
            } elseif (!empty($msg['text'])) {
                $text = (string)$msg['text'];
            }
            if ($text !== '') {
                $this->asrText = $text;
                $this->emit(['type' => 'asr', 'text' => $text, 'final' => false]);
            }
        }
        if ($event === 459) {
            if ($this->asrText !== '') {
                $this->emit(['type' => 'asr', 'text' => $this->asrText, 'final' => true]);
            }
        }
        if (in_array($event, [351, 550], true) && is_array($msg)) {
            $t = '';
            if (!empty($msg['text']) && is_string($msg['text'])) {
                $t = (string)$msg['text'];
            } elseif (!empty($msg['content']) && is_string($msg['content'])) {
                $t = (string)$msg['content'];
            }
            if ($t !== '') {
                if ($event === 351 && !in_array($t, $this->aiSentences, true)) {
                    $this->aiSentences[] = $t;
                    $this->emit(['type' => 'ai_text', 'text' => $t]);
                } elseif ($event === 550 && !$this->aiSentences) {
                    $this->emit(['type' => 'ai_text', 'text' => $t, 'delta' => true]);
                }
            }
        }
        if (!empty($parsed['payload_bytes'])) {
            $mt = (int)($parsed['message_type'] ?? 0);
            if ($event === 352 || $mt === DoubaoProtocol::SERVER_ACK) {
                $this->ttsPcm .= $parsed['payload_bytes'];
            }
        }
        if ($event === 359) {
            $this->flushTts(true);
        }
        if ($event === 154 && is_array($msg)) {
            if (isset($msg['usage']) && is_array($msg['usage'])) {
                $u = $msg['usage'];
                $this->usageTokens += (int)($u['input_audio_tokens'] ?? 0)
                    + (int)($u['input_text_tokens'] ?? 0)
                    + (int)($u['output_audio_tokens'] ?? 0)
                    + (int)($u['output_text_tokens'] ?? 0)
                    + (int)($u['cached_audio_tokens'] ?? 0)
                    + (int)($u['cached_text_tokens'] ?? 0);
            }
        }
        if ($event === 559 || $event === 152) {
            $this->flushTts(true);
            $this->aiSentences = [];
            $this->asrText = '';
            $this->emit(['type' => 'turn_end']);
        }
    }

    protected function flushTts(bool $force): void
    {
        if ($this->ttsPcm === '') {
            return;
        }
        if (!$force && strlen($this->ttsPcm) < 4800) {
            return;
        }
        $wav = DoubaoProtocol::pcmToWav($this->ttsPcm, 24000, 1, 16);
        $this->ttsPcm = '';
        $this->emit([
            'type' => 'tts',
            'audio_wav_base64' => base64_encode($wav),
        ]);
    }

    protected function emit(array $evt): void
    {
        if (!$this->onEvent) {
            return;
        }
        try {
            ($this->onEvent)($evt);
        } catch (\Throwable $e) {
            Log::warning('Doubao live onEvent: ' . $e->getMessage());
        }
    }

    protected function recvUntil(array $events, float $timeoutSec, ?string $pushBinary = null): array
    {
        if ($pushBinary !== null && $this->client) {
            $this->client->push($pushBinary, WEBSOCKET_OPCODE_BINARY);
        }
        $deadline = microtime(true) + $timeoutSec;
        while (microtime(true) < $deadline && $this->client) {
            $frame = $this->client->recv(1);
            if ($frame === false || $frame === '') {
                continue;
            }
            $data = is_object($frame) ? ($frame->data ?? '') : $frame;
            if ($data === '' || $data === true) {
                continue;
            }
            $parsed = DoubaoProtocol::parse((string)$data);
            $event = (int)($parsed['event'] ?? 0);
            if (in_array($event, $events, true)) {
                return $parsed;
            }
            if ($event === 51 || $event === 153) {
                $msg = $parsed['payload_msg'] ?? null;
                $err = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : 'error';
                throw new \RuntimeException('豆包错误: ' . $err);
            }
            // 开场期间也可能先来 TTS
            if ($event) {
                $this->handleFrame((string)$data);
            }
        }
        return [];
    }

    protected function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
            . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }
}
