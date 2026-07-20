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

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\system\Relevance;
use app\common\model\user\UserDialog;
use app\common\repositories\system\RelevanceRepository;

class UserDialogDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserDialog::class;
    }

    /**
     * 获取或创建会话（uid_a < uid_b 保证唯一）
     */
    public function getOrCreate(int $uid1, int $uid2)
    {
        $uidA = min($uid1, $uid2);
        $uidB = max($uid1, $uid2);
        $dialog = $this->getModel()::where('uid_a', $uidA)->where('uid_b', $uidB)->find();
        if (!$dialog) {
            $relationType = $this->calcRelationType($uid1, $uid2);
            $dialog = $this->getModel()::create([
                'uid_a' => $uidA,
                'uid_b' => $uidB,
                'relation_type' => $relationType,
            ]);
        } else {
            $relationType = $this->calcRelationType($uid1, $uid2);
            if ($dialog->relation_type != $relationType) {
                $dialog->relation_type = $relationType;
                $dialog->save();
            }
        }
        return $dialog;
    }

    /**
     * 计算两个用户之间的关系类型
     * 0=陌生人 1=互关 2=A关注B 3=B关注A
     */
    public function calcRelationType(int $uidA, int $uidB): int
    {
        $fansType = RelevanceRepository::TYPE_COMMUNITY_FANS;
        $aFollowsB = Relevance::where('left_id', $uidA)
            ->where('right_id', $uidB)
            ->where('type', $fansType)
            ->count();
        $bFollowsA = Relevance::where('left_id', $uidB)
            ->where('right_id', $uidA)
            ->where('type', $fansType)
            ->count();

        if ($aFollowsB && $bFollowsA) {
            return 1;
        } elseif ($aFollowsB) {
            return 2;
        } elseif ($bFollowsA) {
            return 3;
        }
        return 0;
    }

    /**
     * 重置会话未读数
     */
    public function resetUnread(int $dialogId, int $uid): void
    {
        $dialog = $this->getModel()::where('dialog_id', $dialogId)->find();
        if (!$dialog) {
            return;
        }

        if ($dialog->uid_a == $uid) {
            $dialog->unread_a = 0;
        } elseif ($dialog->uid_b == $uid) {
            $dialog->unread_b = 0;
        } else {
            return;
        }
        $dialog->save();
    }
}
