<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\MerchantMember;

class MerchantMemberDao extends BaseDao
{
    public function search(array $where = [])
    {
        return MerchantMember::getDB()->where($where);
    }

    /**
     * 查询商户会员客户列表，关联用户表并处理客户列表筛选条件。
     */
    public function customerSearch(array $where = [])
    {
        $query = MerchantMember::getDB()->alias('mm')
            ->join('user u', 'u.uid = mm.uid')
            ->leftJoin('user_merchant um', 'um.uid = mm.uid AND um.mer_id = mm.mer_id AND um.status = 1')
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', fn($q) => $q->where('mm.mer_id', (int)$where['mer_id']))
            ->where('mm.is_logoff', 0)
            ->whereNull('u.cancel_time');

        $userType = ($where['user_type'] ?? '') !== ''
            ? $where['user_type']
            : ($where['register_type'] ?? '');
        $payCount = $where['pay_count'] ?? '';
        $userTime = $where['user_time'] ?? '';
        $userTimeType = ($where['user_time_type'] ?? '') !== ''
            ? $where['user_time_type']
            : ($where['time_type'] ?? 'visit');
        $startTime = strtotime((string)($where['start_time'] ?? ''));
        $endTime = strtotime((string)($where['end_time'] ?? ''));
        if (($where['start_time'] ?? '') !== '' && ($where['end_time'] ?? '') !== '' && $startTime !== false && $endTime !== false) {
            $userTime = date('Y/m/d', $startTime) . '-' . date('Y/m/d', $endTime);
        }
        $labelId = $where['label_id'] ?? '';
        $freezeStatus = $where['freeze_status'] ?? '';
        $userIdentity = $where['user_identity'] ?? '';

        return $query
            ->when(($where['uid'] ?? '') !== '', fn($q) => $q->where('u.uid', (int)$where['uid']))
            ->when(($where['keyword'] ?? '') !== '', function ($q) use ($where) {
                $keyword = (string)$where['keyword'];
                $q->where(function ($query) use ($keyword) {
                    $query->where('u.nickname', 'like', '%' . $keyword . '%')
                        ->whereOr('u.uid', 'like', '%' . $keyword . '%')
                        ->whereOr('u.phone', 'like', '%' . $keyword . '%')
                        ->whereOr('u.real_name', 'like', '%' . $keyword . '%');
                });
            })
            ->when(($where['nickname'] ?? '') !== '', fn($q) => $q->whereLike('u.nickname', '%' . $where['nickname'] . '%'))
            ->when(($where['phone'] ?? '') !== '', fn($q) => $q->whereLike('u.phone', '%' . $where['phone'] . '%'))
            ->when(($where['real_name'] ?? '') !== '', fn($q) => $q->whereLike('u.real_name', '%' . $where['real_name'] . '%'))
            ->when(($where['sex'] ?? '') !== '', fn($q) => $q->where('u.sex', (int)$where['sex']))
            ->when(($where['country'] ?? '') !== '', fn($q) => $q->where('u.country', $where['country']))
            ->when($userType !== '', fn($q) => $q->where('u.user_type', $userType))
            ->when(($where['is_promoter'] ?? '') !== '', function ($q) use ($where) {
                $isPromoter = $where['is_promoter'];
                if (is_array($isPromoter)) {
                    $q->whereIn('u.is_promoter', array_map('intval', $isPromoter));
                } else {
                    $q->where('u.is_promoter', (int)$isPromoter);
                }
            })
            ->when(($where['group_id'] ?? '') !== '', fn($q) => $q->where('u.group_id', (int)$where['group_id']))
            ->when(($where['level'] ?? '') !== '', fn($q) => $q->where('mm.level', (int)$where['level']))
            ->when($userIdentity !== '' && $userIdentity !== 'member', fn($q) => $q->whereRaw('1 = 0'))
            ->when($labelId !== '', function ($q) use ($labelId) {
                $labelIds = array_filter(array_map('intval', (array)$labelId), fn($id) => $id > 0);
                if ($labelIds) {
                    $q->where(function ($query) use ($labelIds) {
                        foreach (array_values($labelIds) as $index => $id) {
                            $condition = "CONCAT(',', um.label_id, ',') LIKE '%,$id,%'";
                            if ($index === 0) {
                                $query->whereRaw($condition);
                            } else {
                                $query->whereOrRaw($condition);
                            }
                        }
                    });
                }
            })
            ->when($payCount !== '', function ($q) use ($payCount) {
                if ((int)$payCount <= 0) {
                    $q->whereRaw('COALESCE(um.pay_num, 0) = 0');
                } else {
                    $q->where('um.pay_num', '>=', (int)$payCount);
                }
            })
            ->when($userTime !== '', function ($q) use ($userTime, $userTimeType) {
                $field = match ($userTimeType) {
                    'visit', 'last_visit' => 'um.last_time',
                    'join_time' => 'mm.create_time',
                    default => 'um.create_time',
                };
                getModelTime($q, $userTime, $field);
            })
            ->when($freezeStatus !== '', fn($q) => $q->where('mm.financial_status', (int)!((int)$freezeStatus)));
    }

    protected function getModel(): string
    {
        return MerchantMember::class;
    }
}
