<?php
// +----------------------------------------------------------------------
// | uni-sec-check Sprint 1+2: UGC 内容安全审核门面
// | 小程序端（有 openid）走微信 msgSecCheck V2，其余场景（APP/H5，V1 未接入）
// | 及微信接口异常时兜底走本地敏感词过滤，第三方故障不阻断主流程。
// | 图片/视频审核（mediaSecCheck 异步）不在本次范围，见 eb_ugc_check_task。
// +----------------------------------------------------------------------

namespace crmeb\services\security;

use crmeb\services\security\driver\WechatDriver;
use crmeb\services\SensitiveWordFilter;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class ContentSecurityService
{
    // 微信官方场景枚举，仅这 4 个值；聊天/反馈等无对应场景的按语义就近映射
    const SCENE_PROFILE = 1; // 资料（昵称/签名/认证说明）
    const SCENE_COMMENT = 2; // 评论（商品评价/社区评论）
    const SCENE_FORUM = 3;   // 论坛（社区发帖/简历）
    const SCENE_SOCIAL = 4;  // 社交日志（聊天/反馈）

    /**
     * 文本内容安全检测
     *
     * @param string $content 待检测文本
     * @param int $scene 场景值，用 self::SCENE_* 常量
     * @param string $bizType 业务标识，如 nickname/community_post/chat_msg
     * @param int $bizId 关联的业务主键，没有则传 0
     * @param int $uid 提交内容的用户 id
     * @param string|null $openid 用户小程序 openid，为空则视为 APP/H5（本次走本地兜底）
     * @param bool $hardBlock 判定不通过时是否抛异常硬拦截
     * @return array{pass:bool,driver:string,suggest:string,label:int,trace_id:string}
     * @throws ValidateException 当 $hardBlock=true 且判定不通过
     */
    public static function checkText(
        string $content,
        int $scene,
        string $bizType,
        int $bizId,
        int $uid,
        ?string $openid,
        bool $hardBlock = true
    ): array {
        $content = trim($content);
        if ($content === '') {
            return ['pass' => true, 'driver' => 'skip', 'suggest' => 'pass', 'label' => 100, 'trace_id' => ''];
        }

        $driver = 'local';
        $result = ['pass' => true, 'suggest' => 'pass', 'label' => 100, 'trace_id' => ''];

        try {
            if (!empty($openid)) {
                $checked = WechatDriver::checkTextV2($content, $scene, $openid);
                $result = array_merge($result, $checked);
                $driver = 'wechat_v2';
            } else {
                // APP/H5：V1 尚未接入，先走本地敏感词兜底
                $hit = SensitiveWordFilter::contains($content);
                $result['pass'] = !$hit;
                $result['suggest'] = $hit ? 'risky' : 'pass';
                $driver = 'local';
            }
        } catch (\Throwable $e) {
            // 微信接口故障 / openid 不满足条件等，兜底走本地敏感词，不因第三方问题阻断主流程
            Log::error('ContentSecurityService 兜底(' . $bizType . '): ' . $e->getMessage());
            $hit = SensitiveWordFilter::contains($content);
            $result['pass'] = !$hit;
            $result['suggest'] = $hit ? 'risky' : 'pass';
            $driver = 'local_fallback';
        }

        self::log($uid, $bizType, $bizId, $scene, $driver, $content, $result);

        if (!$result['pass'] && $hardBlock) {
            throw new ValidateException('内容包含敏感信息，请修改后重试');
        }

        return array_merge($result, ['driver' => $driver]);
    }

    private static function log(int $uid, string $bizType, int $bizId, int $scene, string $driver, string $content, array $result): void
    {
        try {
            Db::name('ugc_check_log')->insert([
                'uid' => $uid,
                'biz_type' => $bizType,
                'biz_id' => $bizId,
                'scene' => $scene,
                'driver' => $driver,
                'content_snapshot' => mb_substr($content, 0, 2000),
                'is_pass' => $result['pass'] ? 1 : 0,
                'suggest' => $result['suggest'] ?? 'pass',
                'label' => (int)($result['label'] ?? 100),
                'trace_id' => (string)($result['trace_id'] ?? ''),
                'create_time' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ugc_check_log 写入失败: ' . $e->getMessage());
        }
    }
}
