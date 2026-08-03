<?php
// +----------------------------------------------------------------------
// | 用户端 - AI 点餐通话
// +----------------------------------------------------------------------

namespace app\controller\api\store\aiOrder;

use app\common\repositories\store\aiOrder\AiOrderConfigRepository;
use app\common\repositories\store\aiOrder\AiOrderDialogRepository;
use app\common\repositories\store\aiOrder\AiOrderSessionRepository;
use crmeb\basic\BaseController;
use think\App;

class AiOrderCall extends BaseController
{
    protected $sessionRepo;
    protected $configRepo;
    protected $dialogRepo;

    public function __construct(
        App $app,
        AiOrderSessionRepository $sessionRepo,
        AiOrderConfigRepository $configRepo,
        AiOrderDialogRepository $dialogRepo
    ) {
        parent::__construct($app);
        $this->sessionRepo = $sessionRepo;
        $this->configRepo = $configRepo;
        $this->dialogRepo = $dialogRepo;
    }

    /**
     * GET /api/ai_order/status
     */
    public function status()
    {
        $merId = (int)$this->request->param('mer_id', 0);
        if ($merId <= 0) {
            return app('json')->fail('缺少商家参数');
        }
        $cfg = $this->configRepo->getConfig($merId);
        return app('json')->success([
            'enable' => (int)$cfg['enable'] === 1 && (int)$cfg['platform_open'] === 1 ? 1 : 0,
            'avatar' => $cfg['avatar'],
            'dialect' => $cfg['dialect'],
            'style' => $cfg['style'],
            'can_call' => ((int)$cfg['enable'] === 1 && (int)$cfg['platform_open'] === 1 && (float)$cfg['ai_balance'] >= (float)$cfg['min_balance']) ? 1 : 0,
            'min_balance' => $cfg['min_balance'],
        ]);
    }

    /**
     * POST /api/ai_order/session/start
     */
    public function start()
    {
        $merId = (int)$this->request->param('mer_id', 0);
        $tableId = (int)$this->request->param('table_id', 0);
        $uid = (int)$this->request->uid();
        if ($merId <= 0) {
            return app('json')->fail('缺少商家参数');
        }
        try {
            $data = $this->sessionRepo->create($merId, $uid, $tableId);
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '无法发起通话');
        }
    }

    /**
     * POST /api/ai_order/dialog/hello
     * AI 开场白（返回 wav base64）
     */
    public function hello()
    {
        $sessionNo = trim((string)$this->request->param('session_no', ''));
        if ($sessionNo === '') {
            return app('json')->fail('缺少会话号');
        }
        try {
            $data = $this->dialogRepo->hello($sessionNo, (int)$this->request->uid());
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: 'AI开场失败');
        }
    }

    /**
     * POST /api/ai_order/dialog/speak
     * 上传 PCM base64（16k/mono/s16le）进行一轮对话
     */
    public function speak()
    {
        $sessionNo = trim((string)$this->request->param('session_no', ''));
        $pcmB64 = (string)$this->request->param('pcm_base64', '');
        if ($sessionNo === '' || $pcmB64 === '') {
            return app('json')->fail('缺少录音数据');
        }
        $pcm = base64_decode($pcmB64, true);
        if ($pcm === false || $pcm === '') {
            return app('json')->fail('录音数据无效');
        }
        try {
            $data = $this->dialogRepo->speak($sessionNo, (int)$this->request->uid(), $pcm);
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '对话失败');
        }
    }

    /**
     * POST /api/ai_order/dialog/speak_text
     * 文本一轮（服务端联调；小程序仍走录音 speak）
     */
    public function speakText()
    {
        $sessionNo = trim((string)$this->request->param('session_no', ''));
        $text = trim((string)$this->request->param('text', ''));
        if ($sessionNo === '' || $text === '') {
            return app('json')->fail('缺少会话或文本');
        }
        try {
            $data = $this->dialogRepo->speakText($sessionNo, (int)$this->request->uid(), $text);
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '对话失败');
        }
    }

    /**
     * POST /api/ai_order/session/end
     */
    public function end()
    {
        $sessionNo = trim((string)$this->request->param('session_no', ''));
        if ($sessionNo === '') {
            return app('json')->fail('缺少会话号');
        }
        $uid = (int)$this->request->uid();
        $items = $this->request->param('items', []);
        if (!is_array($items)) {
            $items = [];
        }
        $payload = [
            'usage_tokens' => (int)$this->request->param('usage_tokens', 0),
            'usage_seconds' => (int)$this->request->param('usage_seconds', 0),
            'summary' => (string)$this->request->param('summary', ''),
            'transcript' => (string)$this->request->param('transcript', ''),
            'provider_request_id' => (string)$this->request->param('provider_request_id', ''),
            'items' => $items,
        ];
        // 合并服务端对话稿 + 前端本地字幕（ASR 失败时前端仍可能有用户口述摘要）
        $clientTranscript = trim((string)$payload['transcript']);
        try {
            $fromDialog = $this->dialogRepo->buildEndPayload($sessionNo);
            $serverTranscript = trim((string)($fromDialog['transcript'] ?? ''));
            if ($serverTranscript !== '' && $clientTranscript !== '') {
                $payload['transcript'] = $serverTranscript . "\n" . $clientTranscript;
            } elseif ($serverTranscript !== '') {
                $payload['transcript'] = $serverTranscript;
            } elseif ($clientTranscript !== '') {
                $payload['transcript'] = $clientTranscript;
            }
            if (!empty($fromDialog['summary']) && trim((string)$payload['summary']) === '') {
                $payload['summary'] = $fromDialog['summary'];
            }
            if (trim((string)$payload['summary']) === '' && !empty($payload['transcript'])) {
                $payload['summary'] = '顾客沟通摘要：' . mb_substr((string)$payload['transcript'], 0, 500);
            }
            if ((int)$payload['usage_tokens'] <= 0 && !empty($fromDialog['usage_tokens'])) {
                $payload['usage_tokens'] = (int)$fromDialog['usage_tokens'];
            }
            if (!$items && !empty($fromDialog['items'])) {
                $payload['items'] = $fromDialog['items'];
            }
        } catch (\Throwable $e) {
        }
        try {
            $data = $this->sessionRepo->end($sessionNo, $uid, $payload);
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '结束会话失败');
        }
    }

    /**
     * POST /api/ai_order/session/add_cart
     * 用户确认后，把 AI 建议菜品加入扫码购物车（不代下单）
     */
    public function addCart()
    {
        $sessionNo = trim((string)$this->request->param('session_no', ''));
        $items = $this->request->param('items', []);
        if ($sessionNo === '') {
            return app('json')->fail('缺少会话号');
        }
        if (!is_array($items) || !$items) {
            return app('json')->fail('请选择菜品');
        }
        $uid = (int)$this->request->uid();
        $tourist = (string)$this->request->param('tourist_unique_key', '');
        try {
            $data = $this->sessionRepo->addSuggestToCart($sessionNo, $uid, $items, $tourist);
            return app('json')->success($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '加入购物车失败');
        }
    }
}
