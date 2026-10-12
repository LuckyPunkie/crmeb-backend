<?php

namespace app\controller\api;

use app\common\model\wechat\WechatUser;
use crmeb\basic\BaseController;
use crmeb\services\AppleIdentityService;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * Sign in with Apple 服务器通知（Server-to-Server Notification Endpoint）
 * 苹果 POST {"payload": "<JWT>"}，JWT 内 events 为 JSON 串：{type, sub, event_time, email?, is_private_email?}
 * type：email-disabled / email-enabled / consent-revoked / account-delete
 * 文档：https://developer.apple.com/documentation/sign_in_with_apple/processing_changes_for_sign_in_with_apple_accounts
 */
class AppleNotify extends BaseController
{
    public function notify()
    {
        $jwt = (string)$this->request->param('payload', '');
        try {
            $payload = AppleIdentityService::verify($jwt);
        } catch (ValidateException $e) {
            Log::warning('AppleNotify verify fail: ' . $e->getMessage());
            return json(['ok' => false, 'msg' => 'invalid payload'], 400);
        }

        $event = is_string($payload->events ?? null) ? json_decode($payload->events, true) : (array)($payload->events ?? []);
        $type = (string)($event['type'] ?? '');
        $sub = (string)($event['sub'] ?? '');
        Log::info('AppleNotify event: ' . json_encode($event, JSON_UNESCAPED_UNICODE));

        if ($type === 'account-delete' && $sub !== '') {
            // 用户永久删除了 Apple 账号：该 sub 不会再登录，解除苹果身份绑定（保留商城账号及手机号等其他登录方式）
            $affected = WechatUser::getDB()->where('unionid', $sub)->update(['unionid' => '', 'openid' => '']);
            Log::info('AppleNotify account-delete unbind sub=' . $sub . ' rows=' . $affected);
        }
        // consent-revoked：用户在系统设置里停止使用 Apple 登录本 App。不解绑——用户再次用 Apple 登录时 sub 不变，应回到原账号
        // email-disabled / email-enabled：隐藏邮箱转发开关，商城不依赖苹果邮箱，仅记日志

        return json(['ok' => true]);
    }
}
