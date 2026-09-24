<?php
// +----------------------------------------------------------------------
// | uni-ad 激励视频服务器回调
// | 场景：实名认证前强制观看激励视频，服务器端二次校验，防止客户端伪造 isEnded
// +----------------------------------------------------------------------

namespace app\controller\api;

use crmeb\basic\BaseController;
use think\App;
use think\facade\Cache;
use think\facade\Log;

class UniAdCallback extends BaseController
{
    // uni-ad 后台"激励视频服务器回调"生成的 Secret（2026-09-22）
    private const SECRET = '2628092a6a207a91025229afc0fbdc9cb0c5569d8870d78d865d904bafa7e0cd';

    // Redis key 前缀 & TTL（uni-ad 回调后端记录，前端轮询查询）
    private const CACHE_PREFIX = 'uni_ad:reward:';
    private const CACHE_TTL    = 1800; // 30 分钟

    /**
     * uni-ad 服务器回调（HTTP GET，无需登录）
     * 参数：adpid provider platform sign trans_id user_id extra cpm
     * 签名：sign = sha256("{secret}:{trans_id}")
     * 应答：{"isValid": true}   —— 不返回正确 JSON uni-ad 会重试
     */
    public function rewardCallback()
    {
        $params = $this->request->get();
        $transId = trim($params['trans_id'] ?? '');
        $sign    = trim($params['sign'] ?? '');
        $userId  = trim($params['user_id'] ?? '');
        $adpid   = trim($params['adpid'] ?? '');

        Log::info('[uni-ad][reward_callback] recv ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        if ($transId === '' || $sign === '') {
            return json(['isValid' => false, 'msg' => 'missing params']);
        }

        // 验签：sha256("{SECRET}:{trans_id}")
        $expect = hash('sha256', self::SECRET . ':' . $transId);
        if (!hash_equals($expect, strtolower($sign))) {
            Log::warning('[uni-ad][reward_callback] sign mismatch trans_id=' . $transId);
            return json(['isValid' => false, 'msg' => 'sign mismatch']);
        }

        // 幂等：同 trans_id 只写一次；重复回调直接返回成功即可
        $cacheKey = self::CACHE_PREFIX . $transId;
        if (!Cache::has($cacheKey)) {
            Cache::set($cacheKey, [
                'user_id'  => $userId,
                'adpid'    => $adpid,
                'provider' => $params['provider'] ?? '',
                'platform' => $params['platform'] ?? '',
                'extra'    => $params['extra'] ?? '',
                'cpm'      => (int)($params['cpm'] ?? 0),
                'ts'       => time(),
            ], self::CACHE_TTL);
        }

        return json(['isValid' => true]);
    }

    /**
     * 前端轮询接口（需登录）
     * GET /api/uni_ad/is_rewarded?trans_id=xxx
     * 返回：{"status":200, "data":{"rewarded":true/false}}
     * 校验：cache 里的 user_id 必须与当前登录 uid 一致；命中后立即删除（一次性，防重放）
     */
    public function isRewarded()
    {
        $transId = trim($this->request->get('trans_id/s', ''));
        if ($transId === '') {
            return app('json')->fail('缺少 trans_id');
        }

        $uid = 0;
        try {
            if ($this->request->isLogin()) {
                $userInfo = $this->request->userInfo();
                $uid = (int)($userInfo ? $userInfo->uid : 0);
            }
        } catch (\Throwable $e) {
            $uid = 0;
        }
        if ($uid <= 0) {
            return app('json')->fail('未登录');
        }

        $cacheKey = self::CACHE_PREFIX . $transId;
        $data = Cache::get($cacheKey);
        if (!$data) {
            return app('json')->success(['rewarded' => false]);
        }

        // 客户端传入的 userId 允许是字符串，做宽松比较
        $cacheUid = trim((string)($data['user_id'] ?? ''));
        if ($cacheUid !== '' && $cacheUid !== (string)$uid) {
            Log::warning('[uni-ad][is_rewarded] uid mismatch cache=' . $cacheUid . ' req=' . $uid);
            return app('json')->success(['rewarded' => false]);
        }

        // 一次性使用：删除防重放
        Cache::delete($cacheKey);

        return app('json')->success(['rewarded' => true]);
    }
}
