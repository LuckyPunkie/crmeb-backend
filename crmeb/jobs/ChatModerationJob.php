<?php
// +----------------------------------------------------------------------
// | 私聊文本"先发后审"（《内容安全阈值确定版》5.3）：消息已送达，这里异步审核，
// | 违规则系统撤回并通知发送者
// +----------------------------------------------------------------------

namespace crmeb\jobs;

use app\common\repositories\user\UserMessageRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\security\ContentSecurityService;
use think\facade\Db;
use think\facade\Log;

class ChatModerationJob implements JobInterface
{
    /**
     * @param array{message_id:int,openid:string} $data
     */
    public function fire($job, $data)
    {
        $messageId = (int)($data['message_id'] ?? 0);
        try {
            $msg = Db::name('user_message')->where('message_id', $messageId)->find();
            if ($msg && (int)$msg['msn_type'] === 1 && trim((string)$msg['msn']) !== '') {
                $check = ContentSecurityService::checkText(
                    (string)$msg['msn'],
                    ContentSecurityService::SCENE_SOCIAL,
                    'chat_msg',
                    $messageId,
                    (int)$msg['from_uid'],
                    (string)($data['openid'] ?? ''),
                    false
                );
                if ($check['suggest'] === 'risky'
                    && app()->make(UserMessageRepository::class)->systemRecallMessage($messageId)) {
                    ContentSecurityService::notifyRecalled((int)$msg['from_uid']);
                }
            }
        } catch (\Throwable $e) {
            Log::error("私聊异步审核失败(message#{$messageId}): " . $e->getMessage());
        }
        $job->delete();
    }

    public function failed($data)
    {
    }
}
