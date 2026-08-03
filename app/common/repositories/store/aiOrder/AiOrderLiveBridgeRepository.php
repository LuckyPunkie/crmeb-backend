<?php
// +----------------------------------------------------------------------
// | AI 点餐 - WebSocket 实时语音桥（fd ↔ 豆包长连接）
// +----------------------------------------------------------------------

namespace app\common\repositories\store\aiOrder;

use app\common\model\store\aiOrder\AiOrderSession;
use app\common\services\aiOrder\DoubaoRealtimeLiveSession;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use think\swoole\Websocket;

class AiOrderLiveBridgeRepository
{
    /** @var array<int, array{session:DoubaoRealtimeLiveSession,session_no:string,uid:int,sender:string}> */
    protected static array $bridges = [];

    public function start(int $fd, int $uid, string $sender, string $sessionNo): array
    {
        $sessionNo = trim($sessionNo);
        if ($sessionNo === '') {
            throw new ValidateException('缺少会话号');
        }
        $row = AiOrderSession::getDB()->where('session_no', $sessionNo)->find();
        if (!$row) {
            throw new ValidateException('会话不存在');
        }
        if ((int)$row['status'] !== AiOrderSession::STATUS_ACTIVE) {
            throw new ValidateException('会话已结束');
        }
        if ((int)$row['uid'] > 0 && $uid > 0 && (int)$row['uid'] !== $uid) {
            throw new ValidateException('无权操作');
        }

        $this->stopFd($fd);

        $state = Cache::get('ai_order_dialog_' . $sessionNo);
        $systemPrompt = is_array($state) ? (string)($state['system_prompt'] ?? '') : '';
        if ($systemPrompt === '') {
            $cfg = app()->make(AiOrderConfigRepository::class)->getConfig((int)$row['mer_id']);
            $dialects = config('ai_order.dialects') ?: [];
            $styles = config('ai_order.styles') ?: [];
            $mer = \app\common\model\system\merchant\Merchant::getDB()
                ->where('mer_id', (int)$row['mer_id'])->field('mer_name')->find();
            /** @var AiOrderSessionRepository $sessionRepo */
            $sessionRepo = app()->make(AiOrderSessionRepository::class);
            $systemPrompt = app()->make(\app\common\services\aiOrder\DoubaoRealtimeClient::class)->buildSystemPrompt(
                $mer ? (string)$mer['mer_name'] : '本店',
                $sessionRepo->buildMenuLines((int)$row['mer_id']),
                $dialects[$cfg['dialect']] ?? '普通话',
                $styles[$cfg['style']] ?? '热情亲切'
            );
        }
        $state = is_array($state) ? $state : [
            'turns' => [],
            'usage_tokens' => 0,
            'system_prompt' => '',
            'dialog_ws_id' => '',
            'dialog_id' => '',
        ];
        $state['system_prompt'] = $systemPrompt;
        // 实时模式重开：清空短连接回合，避免重复开场
        $state['turns'] = [];
        Cache::set('ai_order_dialog_' . $sessionNo, $state, 3600);

        $wsId = substr(md5($sessionNo . uniqid('live', true)), 0, 32);
        $live = new DoubaoRealtimeLiveSession();
        $live->start(
            $wsId,
            $systemPrompt,
            '您好，请问几位用餐？有什么忌口吗？',
            function (array $evt) use ($fd, $sessionNo, $sender) {
                $this->onLiveEvent($fd, $sessionNo, $sender, $evt);
            }
        );

        self::$bridges[$fd] = [
            'session' => $live,
            'session_no' => $sessionNo,
            'uid' => $uid,
            'sender' => $sender,
        ];

        return [
            'session_no' => $sessionNo,
            'mode' => 'realtime',
            'dialog_id' => $live->dialogId(),
        ];
    }

    public function feedPcm(int $fd, string $pcmBase64): void
    {
        $bridge = self::$bridges[$fd] ?? null;
        if (!$bridge) {
            return;
        }
        $bin = base64_decode($pcmBase64, true);
        if ($bin === false || $bin === '') {
            return;
        }
        /** @var DoubaoRealtimeLiveSession $live */
        $live = $bridge['session'];
        $live->feedPcm($bin);
    }

    public function stopFd(int $fd): void
    {
        $bridge = self::$bridges[$fd] ?? null;
        if (!$bridge) {
            return;
        }
        unset(self::$bridges[$fd]);
        try {
            /** @var DoubaoRealtimeLiveSession $live */
            $live = $bridge['session'];
            $usage = $live->usageTokens();
            $sessionNo = (string)$bridge['session_no'];
            $live->close();
            if ($usage > 0 && $sessionNo !== '') {
                $state = Cache::get('ai_order_dialog_' . $sessionNo);
                if (is_array($state)) {
                    $state['usage_tokens'] = (int)($state['usage_tokens'] ?? 0) + $usage;
                    Cache::set('ai_order_dialog_' . $sessionNo, $state, 3600);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AI live stop: ' . $e->getMessage());
        }
    }

    public function has(int $fd): bool
    {
        return isset(self::$bridges[$fd]);
    }

    protected function onLiveEvent(int $fd, string $sessionNo, string $sender, array $evt): void
    {
        $type = (string)($evt['type'] ?? '');
        if ($type === '') {
            return;
        }

        // 写对话稿，供挂断总结
        if ($type === 'asr' && !empty($evt['final']) && !empty($evt['text'])) {
            $this->appendTurn($sessionNo, 'user', (string)$evt['text']);
        }
        if ($type === 'ai_text' && empty($evt['delta']) && !empty($evt['text'])) {
            $this->appendTurn($sessionNo, 'assistant', (string)$evt['text']);
        }

        $this->pushFd($fd, $sender, $evt);
    }

    protected function appendTurn(string $sessionNo, string $role, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $state = Cache::get('ai_order_dialog_' . $sessionNo);
        if (!is_array($state)) {
            $state = ['turns' => [], 'usage_tokens' => 0];
        }
        $turns = $state['turns'] ?? [];
        // 同一角色连续短句合并（ASR 增量已在 final 时写入）
        $turns[] = ['role' => $role, 'text' => $text, 'time' => time()];
        $state['turns'] = array_slice($turns, -40);
        Cache::set('ai_order_dialog_' . $sessionNo, $state, 3600);
    }

    protected function pushFd(int $fd, string $sender, array $evt): void
    {
        $payload = json_encode([
            'type' => 'ai_order',
            'data' => $evt,
        ], JSON_UNESCAPED_UNICODE);
        try {
            if ($sender !== '') {
                app()->make(Websocket::class)->to($sender)->push($payload);
                return;
            }
        } catch (\Throwable $e) {
        }
        // fallback
        try {
            $info = Cache::get('_ws_f_' . $fd);
            if (is_array($info) && !empty($info['sender'])) {
                app()->make(Websocket::class)->to($info['sender'])->push($payload);
            }
        } catch (\Throwable $e) {
            Log::info('AI live push failed: ' . $e->getMessage());
        }
        unset($e);
        // DirectPushService 固定 type=chat，这里不用
        unset($fd);
    }
}
