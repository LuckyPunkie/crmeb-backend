<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 回合制语音对话
// +----------------------------------------------------------------------

namespace app\common\repositories\store\aiOrder;

use app\common\model\store\aiOrder\AiOrderSession;
use app\common\services\aiOrder\DoubaoRealtimeClient;
use think\exception\ValidateException;
use think\facade\Cache;

class AiOrderDialogRepository
{
    protected $doubao;
    protected $sessionRepo;

    public function __construct(DoubaoRealtimeClient $doubao, AiOrderSessionRepository $sessionRepo)
    {
        $this->doubao = $doubao;
        $this->sessionRepo = $sessionRepo;
    }

    protected function cacheKey(string $sessionNo): string
    {
        return 'ai_order_dialog_' . $sessionNo;
    }

    protected function loadState(string $sessionNo): array
    {
        $state = Cache::get($this->cacheKey($sessionNo));
        return is_array($state) ? $state : [
            'turns' => [],
            'usage_tokens' => 0,
            'system_prompt' => '',
            'dialog_ws_id' => '',
            'dialog_id' => '',
        ];
    }

    /** 把近期对话塞进 system_role，弥补短连接无记忆 */
    protected function promptWithHistory(string $basePrompt, array $turns): string
    {
        if (!$turns) {
            return $basePrompt;
        }
        $lines = [];
        foreach (array_slice($turns, -12) as $t) {
            $role = ($t['role'] ?? '') === 'user' ? '顾客' : '服务员';
            $text = trim((string)($t['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $lines[] = $role . '：' . $text;
        }
        if (!$lines) {
            return $basePrompt;
        }
        return $basePrompt . "\n\n以下是刚才的对话，请承接继续点餐：\n" . implode("\n", $lines);
    }

    protected function saveState(string $sessionNo, array $state): void
    {
        Cache::set($this->cacheKey($sessionNo), $state, 3600);
    }

    public function assertActiveSession(string $sessionNo, int $uid): array
    {
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
        return $row->toArray();
    }

    public function hello(string $sessionNo, int $uid): array
    {
        $session = $this->assertActiveSession($sessionNo, $uid);
        if (!$this->doubao->isConfigured()) {
            throw new ValidateException('豆包密钥未配置');
        }
        $state = $this->loadState($sessionNo);
        if (empty($state['system_prompt'])) {
            $cfg = app()->make(AiOrderConfigRepository::class)->getConfig((int)$session['mer_id']);
            $dialects = config('ai_order.dialects') ?: [];
            $styles = config('ai_order.styles') ?: [];
            $mer = \app\common\model\system\merchant\Merchant::getDB()
                ->where('mer_id', (int)$session['mer_id'])->field('mer_name')->find();
            $menu = $this->sessionRepo->buildMenuLines((int)$session['mer_id']);
            $state['system_prompt'] = $this->doubao->buildSystemPrompt(
                $mer ? (string)$mer['mer_name'] : '本店',
                $menu,
                $dialects[$cfg['dialect']] ?? '普通话',
                $styles[$cfg['style']] ?? '热情亲切'
            );
            $this->saveState($sessionNo, $state);
        }

        $wsId = substr(md5($sessionNo . microtime(true)), 0, 32);
        $round = $this->doubao->sayHello(
            $wsId,
            $state['system_prompt'],
            '您好，请问几位用餐？有什么忌口吗？',
            (string)($state['dialog_id'] ?? '')
        );
        $state['usage_tokens'] = (int)$state['usage_tokens'] + max(1, (int)$round['usage_tokens']);
        $state['turns'][] = [
            'role' => 'assistant',
            'text' => $round['ai_text'] ?: '您好，请问几位用餐？',
            'time' => time(),
        ];
        $state['dialog_ws_id'] = $wsId;
        if (!empty($round['dialog_id'])) {
            $state['dialog_id'] = (string)$round['dialog_id'];
        }
        $this->saveState($sessionNo, $state);

        return [
            'session_no' => $sessionNo,
            'ai_text' => $round['ai_text'] ?: '您好，请问几位用餐？有什么忌口吗？',
            'asr_text' => '',
            'audio_wav_base64' => $round['audio_wav_base64'],
            'turns' => $state['turns'],
        ];
    }

    public function speak(string $sessionNo, int $uid, string $pcmBinary): array
    {
        $this->assertActiveSession($sessionNo, $uid);
        if (strlen($pcmBinary) < 1600) {
            throw new ValidateException('录音太短，请按住再说一会儿');
        }
        $state = $this->loadState($sessionNo);
        $prompt = (string)($state['system_prompt'] ?? '');
        if ($prompt === '') {
            $prompt = $this->doubao->buildSystemPrompt('本店', [], '普通话', '热情亲切');
            $state['system_prompt'] = $prompt;
        }
        $prompt = $this->promptWithHistory($prompt, $state['turns'] ?? []);
        $wsId = substr(md5($sessionNo . uniqid('', true)), 0, 32);
        $round = $this->doubao->speakPcm(
            $wsId,
            $prompt,
            $pcmBinary,
            (string)($state['dialog_id'] ?? '')
        );
        // ASR 无结果时，不硬失败：若仍有 AI 语音则继续；否则提示重说
        if ($round['asr_text'] === '' && $round['audio_wav_base64'] === '' && $round['ai_text'] === '') {
            throw new ValidateException('没听清，请靠近麦克风再按住说一次');
        }
        $state['usage_tokens'] = (int)$state['usage_tokens'] + max(1, (int)$round['usage_tokens']);
        if ($round['asr_text'] !== '') {
            $state['turns'][] = ['role' => 'user', 'text' => $round['asr_text'], 'time' => time()];
        }
        if ($round['ai_text'] !== '') {
            $state['turns'][] = ['role' => 'assistant', 'text' => $round['ai_text'], 'time' => time()];
        }
        if (!empty($round['dialog_id'])) {
            $state['dialog_id'] = (string)$round['dialog_id'];
        }
        $this->saveState($sessionNo, $state);

        return [
            'session_no' => $sessionNo,
            'asr_text' => $round['asr_text'],
            'ai_text' => $round['ai_text'],
            'audio_wav_base64' => $round['audio_wav_base64'],
            'turns' => $state['turns'],
            'usage_tokens' => (int)$state['usage_tokens'],
        ];
    }

    /** 文本对话（服务端联调 / 备用） */
    public function speakText(string $sessionNo, int $uid, string $userText): array
    {
        $this->assertActiveSession($sessionNo, $uid);
        $userText = trim($userText);
        if ($userText === '') {
            throw new ValidateException('请输入内容');
        }
        $state = $this->loadState($sessionNo);
        $prompt = (string)($state['system_prompt'] ?? '');
        if ($prompt === '') {
            $prompt = $this->doubao->buildSystemPrompt('本店', [], '普通话', '热情亲切');
            $state['system_prompt'] = $prompt;
        }
        $prompt = $this->promptWithHistory($prompt, $state['turns'] ?? []);
        $wsId = substr(md5($sessionNo . uniqid('t', true)), 0, 32);
        $round = $this->doubao->speakText(
            $wsId,
            $prompt,
            $userText,
            (string)($state['dialog_id'] ?? '')
        );
        $state['usage_tokens'] = (int)$state['usage_tokens'] + max(1, (int)$round['usage_tokens']);
        $state['turns'][] = ['role' => 'user', 'text' => $userText, 'time' => time()];
        if ($round['ai_text'] !== '') {
            $state['turns'][] = ['role' => 'assistant', 'text' => $round['ai_text'], 'time' => time()];
        }
        if (!empty($round['dialog_id'])) {
            $state['dialog_id'] = (string)$round['dialog_id'];
        }
        $this->saveState($sessionNo, $state);
        return [
            'session_no' => $sessionNo,
            'asr_text' => $userText,
            'ai_text' => $round['ai_text'],
            'audio_wav_base64' => $round['audio_wav_base64'],
            'turns' => $state['turns'],
            'usage_tokens' => (int)$state['usage_tokens'],
        ];
    }

    public function buildEndPayload(string $sessionNo): array
    {
        $state = $this->loadState($sessionNo);
        $turns = $state['turns'] ?? [];
        $lines = [];
        foreach ($turns as $t) {
            $role = ($t['role'] ?? '') === 'user' ? '顾客' : '服务员';
            $lines[] = $role . '：' . ($t['text'] ?? '');
        }
        $transcript = implode("\n", $lines);
        $summary = $this->buildSummary($turns);
        return [
            'usage_tokens' => (int)($state['usage_tokens'] ?? 0),
            'transcript' => $transcript,
            'summary' => $summary,
            'items' => $this->extractItemsFromTurns($sessionNo, $turns),
        ];
    }

    protected function buildSummary(array $turns): string
    {
        $userBits = [];
        foreach ($turns as $t) {
            if (($t['role'] ?? '') === 'user' && !empty($t['text'])) {
                $userBits[] = $t['text'];
            }
        }
        if (!$userBits) {
            return '顾客沟通摘要：顾客进行了AI点餐沟通。';
        }
        $text = implode('；', $userBits);
        if (mb_strlen($text) > 400) {
            $text = mb_substr($text, 0, 400) . '…';
        }
        return '顾客沟通摘要：' . $text;
    }

    protected function extractItemsFromTurns(string $sessionNo, array $turns): array
    {
        $session = AiOrderSession::getDB()->where('session_no', $sessionNo)->find();
        if (!$session) {
            return [];
        }
        // 只按顾客原话匹配，且排除「不要/不需要」的菜，避免把服务员推荐塞进购物车
        return $this->sessionRepo->resolveSuggestFromTurns((int)$session['mer_id'], $turns, false);
    }

    public function bindSystemPrompt(string $sessionNo, string $prompt): void
    {
        $state = $this->loadState($sessionNo);
        $state['system_prompt'] = $prompt;
        $this->saveState($sessionNo, $state);
    }
}
