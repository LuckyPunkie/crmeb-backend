<?php
// +----------------------------------------------------------------------
// | 豆包端到端实时语音（服务端短连接回合制）
// +----------------------------------------------------------------------

namespace app\common\services\aiOrder;

use think\facade\Log;

class DoubaoRealtimeClient
{
    public const WS_HOST = 'openspeech.bytedance.com';
    public const WS_PATH = '/api/v3/realtime/dialogue';

    public function appId(): string
    {
        return (string)config('ai_order.doubao_app_id');
    }

    public function accessToken(): string
    {
        return (string)config('ai_order.doubao_access_token');
    }

    public function isConfigured(): bool
    {
        return $this->appId() !== '' && $this->accessToken() !== '';
    }

    public function buildClientSessionPayload(string $sessionNo, array $context = []): array
    {
        return [
            'session_no' => $sessionNo,
            'provider' => 'doubao_realtime',
            'configured' => $this->isConfigured() ? 1 : 0,
            'mode' => 'push_to_talk',
            'input_sample_rate' => 16000,
            'output_sample_rate' => 24000,
            'system_prompt' => $context['system_prompt'] ?? '',
            'dialect' => $context['dialect'] ?? 'mandarin',
            'style' => $context['style'] ?? 'friendly',
        ];
    }

    public function buildSystemPrompt(string $merName, array $menuLines, string $dialectLabel, string $styleLabel): string
    {
        $menu = $menuLines ? implode("\n", array_slice($menuLines, 0, 80)) : '暂无菜品信息';
        return "你是「{$merName}」餐厅的AI点餐服务员，使用{$dialectLabel}交流，语气{$styleLabel}。"
            . "根据本店菜品与顾客沟通点餐，只推荐清单内菜品，询问人数、口味、忌口，主动给搭配建议。"
            . "不要替顾客支付；沟通结束后系统会把需求整理成文字。"
            . "回答尽量简短口语化，每次不超过40字。\n\n本店菜品：\n{$menu}";
    }

    /**
     * 开场白：AI 先说话
     * @return array{asr_text:string,ai_text:string,audio_wav_base64:string,usage_tokens:int,dialog_id:string}
     */
    public function sayHello(string $dialogSessionId, string $systemRole, string $helloText = '您好，请问几位用餐？有什么忌口吗？', string $dialogId = ''): array
    {
        return $this->runRound($dialogSessionId, $systemRole, null, $helloText, true, $dialogId);
    }

    /**
     * 用户 PCM(16k s16le) 一轮对话
     */
    public function speakPcm(string $dialogSessionId, string $systemRole, string $pcm16k, string $dialogId = ''): array
    {
        return $this->runRound($dialogSessionId, $systemRole, $pcm16k, '', false, $dialogId);
    }

    /**
     * 文本一轮（联调 / ASR 失败兜底）
     */
    public function speakText(string $dialogSessionId, string $systemRole, string $userText, string $dialogId = ''): array
    {
        return $this->runRound($dialogSessionId, $systemRole, null, $userText, false, $dialogId, true);
    }

    protected function runRound(
        string $sessionId,
        string $systemRole,
        ?string $pcm,
        string $helloOrText,
        bool $isHello,
        string $dialogId = '',
        bool $textQuery = false
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('豆包密钥未配置');
        }
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('需要 Swoole 扩展以连接豆包实时语音');
        }

        $result = [
            'asr_text' => '',
            'ai_text' => '',
            'audio_wav_base64' => '',
            'usage_tokens' => 0,
            'dialog_id' => $dialogId,
            'raw_events' => [],
        ];

        $runner = function () use ($sessionId, $systemRole, $pcm, $helloOrText, $isHello, $dialogId, $textQuery, &$result) {
            $client = new \Swoole\Coroutine\Http\Client(self::WS_HOST, 443, true);
            $client->set([
                'timeout' => 60,
                'websocket_mask' => true,
            ]);
            $client->setHeaders([
                'X-Api-App-ID' => $this->appId(),
                'X-Api-Access-Key' => $this->accessToken(),
                'X-Api-Resource-Id' => 'volc.speech.dialog',
                'X-Api-App-Key' => 'PlgvMymc7f3tQnJ6',
                'X-Api-Connect-Id' => $this->uuid(),
            ]);
            if (!$client->upgrade(self::WS_PATH)) {
                throw new \RuntimeException('豆包 WebSocket 升级失败: ' . $client->errMsg);
            }

            // StartConnection event=1
            $client->push(DoubaoProtocol::packEvent(1, '', new \stdClass()), WEBSOCKET_OPCODE_BINARY);
            $this->recvUntil($client, [50], 5);

            // 小程序是「录完再上传」：必须用 audio_file；push_to_talk 仅适合实时流
            $inputMod = 'audio_file';
            if ($isHello || $textQuery) {
                $inputMod = 'text';
            }

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
                ],
                'dialog' => [
                    'bot_name' => '点餐服务员',
                    'system_role' => $systemRole,
                    'speaking_style' => '热情、简洁',
                    'dialog_id' => $dialogId,
                    'extra' => [
                        'input_mod' => $inputMod,
                    ],
                ],
                'extra' => [
                    'model' => 'O',
                ],
            ];
            $sessionStarted = $this->recvUntil(
                $client,
                [150],
                8,
                DoubaoProtocol::packEvent(100, $sessionId, $startSession)
            );
            if (is_array($sessionStarted['payload_msg'] ?? null) && !empty($sessionStarted['payload_msg']['dialog_id'])) {
                $result['dialog_id'] = (string)$sessionStarted['payload_msg']['dialog_id'];
            }

            $pcmChunks = '';
            $aiText = '';
            $aiSentences = [];
            $asrText = '';
            $usage = 0;
            $gotTtsEnd = false;
            $asrEndedAt = 0.0;
            $gotAnyAudio = false;
            $ttsEndAt = 0.0;
            $gotAnyEvent = false;
            $shouldStop = false;

            $handleParsed = function (array $parsed) use (
                &$result,
                &$pcmChunks,
                &$aiText,
                &$aiSentences,
                &$asrText,
                &$usage,
                &$gotTtsEnd,
                &$asrEndedAt,
                &$gotAnyAudio,
                &$ttsEndAt,
                &$gotAnyEvent,
                &$shouldStop
            ): void {
                $event = (int)($parsed['event'] ?? 0);
                $msg = $parsed['payload_msg'] ?? null;
                if ($event) {
                    $result['raw_events'][] = $event;
                    $gotAnyEvent = true;
                }
                if ($event === 51 || $event === 153) {
                    $err = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : 'session error';
                    throw new \RuntimeException('豆包错误: ' . $err);
                }
                if ($event === 451 && is_array($msg)) {
                    $results = $msg['results'] ?? [];
                    if ($results) {
                        $last = $results[count($results) - 1];
                        if (!empty($last['text'])) {
                            $asrText = (string)$last['text'];
                        }
                    } elseif (!empty($msg['text'])) {
                        $asrText = (string)$msg['text'];
                    }
                }
                if ($event === 459) {
                    $asrEndedAt = microtime(true);
                }
                if (in_array($event, [351, 550], true) && is_array($msg)) {
                    $t = '';
                    if (!empty($msg['text']) && is_string($msg['text'])) {
                        $t = (string)$msg['text'];
                    } elseif (!empty($msg['content']) && is_string($msg['content'])) {
                        $t = (string)$msg['content'];
                    }
                    if ($t !== '') {
                        if ($event === 351) {
                            if (!in_array($t, $aiSentences, true)) {
                                $aiSentences[] = $t;
                            }
                        } elseif (!$aiSentences) {
                            $aiText .= $t;
                        }
                    }
                }
                if (!empty($parsed['payload_bytes'])) {
                    $mt = (int)($parsed['message_type'] ?? 0);
                    if ($event === 352 || $mt === DoubaoProtocol::SERVER_ACK) {
                        $pcmChunks .= $parsed['payload_bytes'];
                        $gotAnyAudio = true;
                        if ($ttsEndAt > 0) {
                            $ttsEndAt = 0.0;
                        }
                    }
                }
                if ($event === 154 && is_array($msg)) {
                    if (isset($msg['usage']) && is_array($msg['usage'])) {
                        $u = $msg['usage'];
                        $usage = (int)($u['input_audio_tokens'] ?? 0)
                            + (int)($u['input_text_tokens'] ?? 0)
                            + (int)($u['output_audio_tokens'] ?? 0)
                            + (int)($u['output_text_tokens'] ?? 0)
                            + (int)($u['cached_audio_tokens'] ?? 0)
                            + (int)($u['cached_text_tokens'] ?? 0);
                    } else {
                        $usage = (int)($msg['total_tokens'] ?? $msg['tokens'] ?? 0);
                    }
                }
                if ($event === 150 && is_array($msg) && !empty($msg['dialog_id'])) {
                    $result['dialog_id'] = (string)$msg['dialog_id'];
                }
                if ($event === 359) {
                    $ttsEndAt = microtime(true);
                }
                if ($event === 559 || $event === 152) {
                    // 有字幕但音频还没到：先别断，再等一会儿收 TTS
                    if (!$gotAnyAudio && ($aiSentences || $aiText !== '')) {
                        $ttsEndAt = microtime(true);
                    } else {
                        $gotTtsEnd = true;
                        $shouldStop = true;
                    }
                }
            };

            // 推音频时必须顺带读回包，否则 TCP/WS 窗口堵死，后续 events=[] 空等
            $drainOnce = function (float $timeout = 0.001) use ($client, $handleParsed, &$shouldStop): void {
                if ($shouldStop) {
                    return;
                }
                $frame = $client->recv($timeout);
                if ($frame === false || $frame === '') {
                    return;
                }
                $data = is_object($frame) ? ($frame->data ?? '') : $frame;
                if ($data === '' || $data === true) {
                    return;
                }
                $handleParsed(DoubaoProtocol::parse((string)$data));
            };

            if ($isHello) {
                $client->push(DoubaoProtocol::packEvent(300, $sessionId, ['content' => $helloOrText]), WEBSOCKET_OPCODE_BINARY);
            } elseif ($textQuery) {
                $client->push(DoubaoProtocol::packEvent(501, $sessionId, ['content' => $helloOrText]), WEBSOCKET_OPCODE_BINARY);
                $asrText = $helloOrText;
            } else {
                // 20ms @16k/16bit/mono = 640 bytes；边推边抽空回包
                $chunk = 640;
                $len = strlen((string)$pcm);
                if ($len < $chunk) {
                    $pcm = str_pad((string)$pcm, $chunk, "\0");
                    $len = strlen($pcm);
                }
                for ($i = 0; $i < $len; $i += $chunk) {
                    if ($shouldStop) {
                        break;
                    }
                    $part = substr($pcm, $i, $chunk);
                    if (strlen($part) < $chunk) {
                        $part = str_pad($part, $chunk, "\0");
                    }
                    $client->push(DoubaoProtocol::packAudio($sessionId, $part), WEBSOCKET_OPCODE_BINARY);
                    // 每帧抽空一次；节奏略快于实时即可
                    $drainOnce(0.001);
                    \Swoole\Coroutine::sleep(0.008);
                }
                if (!$shouldStop) {
                    $client->push(DoubaoProtocol::packEvent(400, $sessionId, new \stdClass()), WEBSOCKET_OPCODE_BINARY);
                }
            }

            $deadline = microtime(true) + 35;
            $recvStarted = microtime(true);
            while (!$shouldStop && microtime(true) < $deadline) {
                $frame = $client->recv(2);
                if ($frame === false || $frame === '') {
                    $idle = microtime(true) - $recvStarted;
                    if (!$gotAnyEvent && $idle > 10) {
                        break;
                    }
                    if ($asrEndedAt > 0 && (microtime(true) - $asrEndedAt) > 6 && !$gotAnyAudio && $aiText === '' && $aiSentences === []) {
                        break;
                    }
                    if ($ttsEndAt > 0 && (microtime(true) - $ttsEndAt) > 2.5) {
                        $gotTtsEnd = true;
                        break;
                    }
                    continue;
                }
                $data = is_object($frame) ? ($frame->data ?? '') : $frame;
                if ($data === '' || $data === true) {
                    continue;
                }
                $handleParsed(DoubaoProtocol::parse((string)$data));
            }

            // FinishSession / FinishConnection
            try {
                $client->push(DoubaoProtocol::packEvent(102, $sessionId, new \stdClass()), WEBSOCKET_OPCODE_BINARY);
                $client->push(DoubaoProtocol::packEvent(2, '', new \stdClass()), WEBSOCKET_OPCODE_BINARY);
            } catch (\Throwable $e) {
            }
            $client->close();

            if ($aiSentences) {
                $aiText = implode('', $aiSentences);
            }
            // 偶发整段双份拼接
            $len = mb_strlen($aiText);
            if ($len >= 8 && $len % 2 === 0) {
                $half = mb_substr($aiText, 0, (int)($len / 2));
                if ($half !== '' && ($half . $half) === $aiText) {
                    $aiText = $half;
                }
            }
            // hello 用 ChatTTSText 时，若未解析到字幕则回落播报文案
            if ($isHello && $aiText === '' && $helloOrText !== '') {
                $aiText = $helloOrText;
            }

            $result['asr_text'] = $asrText;
            $result['ai_text'] = $aiText;
            $result['usage_tokens'] = max($usage, $gotTtsEnd || $pcmChunks !== '' ? 1 : 0);
            if ($pcmChunks !== '') {
                $wav = DoubaoProtocol::pcmToWav($pcmChunks, 24000, 1, 16);
                $result['audio_wav_base64'] = base64_encode($wav);
            }
        };

        if (\Swoole\Coroutine::getCid() > 0) {
            $runner();
        } else {
            \Swoole\Coroutine\run($runner);
        }

        if ($result['audio_wav_base64'] === '' && $result['ai_text'] === '' && $result['asr_text'] === '') {
            Log::warning('Doubao round empty events=' . json_encode($result['raw_events'] ?? []));
            throw new \RuntimeException('没听清您说的内容，请靠近麦克风、按住多说一两秒再试');
        }
        // 只有 ASR 没有回复时也算一轮可用（前端可提示重说）
        if ($result['audio_wav_base64'] === '' && $result['ai_text'] === '' && $result['asr_text'] !== '') {
            $result['ai_text'] = '好的，我听到了：' . $result['asr_text'] . '。还需要点什么吗？';
        }
        unset($result['raw_events']);
        return $result;
    }

    protected function recvUntil(
        \Swoole\Coroutine\Http\Client $client,
        array $events,
        float $timeoutSec,
        ?string $pushBinary = null
    ): array {
        if ($pushBinary !== null) {
            $client->push($pushBinary, WEBSOCKET_OPCODE_BINARY);
        }
        $deadline = microtime(true) + $timeoutSec;
        while (microtime(true) < $deadline) {
            $frame = $client->recv(2);
            if ($frame === false || $frame === '') {
                continue;
            }
            $data = is_object($frame) ? ($frame->data ?? '') : $frame;
            if ($data === '' || $data === true) {
                continue;
            }
            $parsed = DoubaoProtocol::parse((string)$data);
            $event = (int)($parsed['event'] ?? 0);
            if ($event === 51 || $event === 153) {
                $err = is_array($parsed['payload_msg'] ?? null)
                    ? json_encode($parsed['payload_msg'], JSON_UNESCAPED_UNICODE)
                    : 'connection/session failed';
                throw new \RuntimeException('豆包错误: ' . $err);
            }
            if (in_array($event, $events, true)) {
                return $parsed;
            }
        }
        throw new \RuntimeException('等待豆包事件超时: ' . implode(',', $events));
    }

    protected function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
