<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\user;

use app\common\dao\user\UserDialogDao;
use app\common\dao\user\UserMessageDao;
use app\common\repositories\BaseRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @mixin UserMessageDao
 */
class UserMessageRepository extends BaseRepository
{
    public function __construct(UserMessageDao $dao)
    {
        $this->dao = $dao;
    }

    public function getHistory(int $dialogId, int $myUid, int $page, int $limit): array
    {
        $list = $this->dao->getHistory($dialogId, $page, $limit);
        $result = [];
        foreach ($list as $msg) {
            $item = $msg->toArray();
            $item['send_time'] = strtotime($item['create_time']);
            $item['send_date'] = date('H:i', strtotime($item['create_time']));
            $result[] = $item;
        }
        return array_reverse($result);
    }

    public function sendMessage(int $fromUid, int $toUid, array $data): array
    {
        $dialogDao = app()->make(UserDialogDao::class);
        $dialog = $dialogDao->getOrCreate($fromUid, $toUid);
        $dialogId = $dialog->dialog_id;

        if ($dialog->uid_a == $fromUid ? $dialog->is_black_b : $dialog->is_black_a) {
            throw new ValidateException('对方已将你拉黑，无法发送消息');
        }

        $myRelation = $this->getMyRelationType($dialog, $fromUid);
        if ($myRelation == 0) {
            $unrepliedCount = $this->dao->getUnrepliedCount($fromUid, $toUid);
            if ($unrepliedCount >= 3) {
                throw new ValidateException('对方关注或回复你之前，只能发送3条消息');
            }
        }

        $messageData = [
            'dialog_id'      => $dialogId,
            'from_uid'       => $fromUid,
            'to_uid'         => $toUid,
            'msn_type'       => $data['msn_type'],
            'msn'            => $data['msn'],
            'voice_duration' => $data['voice_duration'] ?? 0,
            'ref_note_id'    => $data['ref_note_id'] ?? 0,
        ];
        $message = $this->dao->create($messageData);

        if ((int)$data['msn_type'] === 1) {
            $dialog->last_message = $data['msn'];
        } elseif ((int)$data['msn_type'] === 3) {
            $dialog->last_message = '[图片]';
        } elseif ((int)$data['msn_type'] === 9) {
            $dialog->last_message = '[语音]';
        } else {
            $dialog->last_message = '[消息]';
        }
        $dialog->last_message_type = $data['msn_type'];
        $dialog->last_message_time = date('Y-m-d H:i:s');
        $dialog->last_sender_uid = $fromUid;

        if ($dialog->uid_a == $toUid) {
            $dialog->unread_a += 1;
        } else {
            $dialog->unread_b += 1;
        }
        if ($dialog->uid_a == $toUid && $dialog->is_clear_a) {
            $dialog->is_clear_a = 0;
        }
        if ($dialog->uid_b == $toUid && $dialog->is_clear_b) {
            $dialog->is_clear_b = 0;
        }
        $dialog->save();

        $userInfo = Db::name('user')->field('uid,nickname,avatar')->where('uid', $fromUid)->find();

        SwooleTaskService::user($toUid, [
            'type' => 'user_message',
            'data' => [
                'message_id'    => $message->message_id,
                'dialog_id'     => $dialogId,
                'from_uid'      => $fromUid,
                'from_avatar'   => $userInfo['avatar'] ?? '',
                'from_nickname' => $userInfo['nickname'] ?? '',
                'to_uid'        => $toUid,
                'msn_type'      => $data['msn_type'],
                'msn'           => $data['msn'],
                'voice_duration'=> $data['voice_duration'] ?? 0,
                'ref_note_id'   => $data['ref_note_id'] ?? 0,
                'create_time'   => $message->create_time,
            ],
        ]);

        $messageResult = $message->toArray();
        $messageResult['send_time'] = strtotime($messageResult['create_time']);
        $messageResult['send_date'] = date('H:i', strtotime($messageResult['create_time']));

        return $messageResult;
    }

    public function recallMessage(int $messageId, int $uid): void
    {
        $msg = $this->dao->get($messageId);
        if (!$msg || $msg->from_uid != $uid) {
            throw new ValidateException('无权撤回此消息');
        }
        if (strtotime($msg->create_time) < strtotime('-120 seconds')) {
            throw new ValidateException('消息已超过2分钟，无法撤回');
        }
        $msg->msn_type = 100;
        $msg->msn = '';
        $msg->save();

        SwooleTaskService::user($msg->to_uid, [
            'type' => 'user_message_recall',
            'data' => ['message_id' => $messageId, 'dialog_id' => $msg->dialog_id],
        ]);
    }

    public function markAsRead(int $dialogId, int $toUid): void
    {
        $this->dao->markAsRead($dialogId, $toUid);
        app()->make(UserDialogDao::class)->resetUnread($dialogId, $toUid);
    }

    private function getMyRelationType($dialog, int $myUid): int
    {
        $isMeA = ($dialog->uid_a == $myUid);
        if ($dialog->relation_type == 1) {
            return 1;
        }
        if ($isMeA) {
            if ($dialog->relation_type == 2) {
                return 2;
            }
            if ($dialog->relation_type == 3) {
                return 3;
            }
        } else {
            if ($dialog->relation_type == 2) {
                return 3;
            }
            if ($dialog->relation_type == 3) {
                return 2;
            }
        }
        return 0;
    }
}
