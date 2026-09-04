<?php

namespace app\common\repositories\user;

use think\facade\Db;

/**
 * 用户资料筛选（社区用户列表 / 消息会话列表共用）
 */
trait UserProfileFilterTrait
{
    /**
     * 根据资料筛选条件返回 uid 列表；无资料类筛选时返回 null（表示不过滤）
     */
    protected function filterUidsByUserProfile(array $where): ?array
    {
        $hasProfileFilter = false;
        $profileQuery = Db::name('user_profile');

        if (!empty($where['weight_min']) || !empty($where['weight_max'])) {
            $hasProfileFilter = true;
            $wMin = max(0, (int)($where['weight_min'] ?? 0));
            $wMax = max(0, (int)($where['weight_max'] ?? 0));
            $profileQuery->where('weight', '>', 0);
            if ($wMin > 0) {
                $profileQuery->where('weight', '>=', $wMin);
            }
            if ($wMax > 0) {
                $profileQuery->where('weight', '<=', $wMax);
            }
        }

        if (!empty($where['zodiac'])) {
            $hasProfileFilter = true;
            $profileQuery->where('zodiac', (int)$where['zodiac']);
        }

        if (!empty($where['school_name'])) {
            $hasProfileFilter = true;
            $profileQuery->whereLike('school_name', '%' . trim((string)$where['school_name']) . '%');
        }

        if (!empty($where['job_title'])) {
            $hasProfileFilter = true;
            $profileQuery->whereLike('job_title', '%' . trim((string)$where['job_title']) . '%');
        }

        if (!empty($where['hometown_province'])) {
            $hasProfileFilter = true;
            $profileQuery->where('hometown_province', trim((string)$where['hometown_province']));
        }
        if (!empty($where['hometown_city'])) {
            $hasProfileFilter = true;
            $profileQuery->where('hometown_city', trim((string)$where['hometown_city']));
        }
        if (!empty($where['current_province'])) {
            $hasProfileFilter = true;
            $profileQuery->where('current_province', trim((string)$where['current_province']));
        }
        if (!empty($where['current_city'])) {
            $hasProfileFilter = true;
            $profileQuery->where('current_city', trim((string)$where['current_city']));
        }

        if (!empty($where['annual_income'])) {
            $hasProfileFilter = true;
            $profileQuery->where('annual_income', (int)$where['annual_income']);
        }

        if (!empty($where['relationship_status'])) {
            $hasProfileFilter = true;
            $profileQuery->where('relationship_status', (int)$where['relationship_status']);
        }
        if (!empty($where['relationship_status_not'])) {
            $hasProfileFilter = true;
            $profileQuery->where('relationship_status', '<>', 1);
        }

        if (!empty($where['marital_status'])) {
            $hasProfileFilter = true;
            $profileQuery->where('marital_status', (int)$where['marital_status']);
        }

        if (!empty($where['dating_purpose'])) {
            $hasProfileFilter = true;
            $purposes = is_array($where['dating_purpose'])
                ? $where['dating_purpose']
                : array_filter(explode(',', (string)$where['dating_purpose']));
            $purposes = array_values(array_filter(array_map('intval', $purposes)));
            if ($purposes) {
                $profileQuery->whereIn('dating_purpose', $purposes);
            }
        }

        if (isset($where['car_has']) && $where['car_has'] !== '') {
            $hasProfileFilter = true;
            if ((int)$where['car_has'] === 1) {
                $profileQuery->where('car_count', '>', 0);
            } else {
                $profileQuery->where(function ($q) {
                    $q->whereNull('car_count')->whereOr('car_count', '<=', 0);
                });
            }
        }

        if (isset($where['house_has']) && $where['house_has'] !== '') {
            $hasProfileFilter = true;
            if ((int)$where['house_has'] === 1) {
                $profileQuery->where('house_count', '>', 0);
            } else {
                $profileQuery->where(function ($q) {
                    $q->whereNull('house_count')->whereOr('house_count', '<=', 0);
                });
            }
        }

        if (!empty($where['total_assets'])) {
            $hasProfileFilter = true;
            $assets = is_array($where['total_assets'])
                ? $where['total_assets']
                : array_filter(explode(',', (string)$where['total_assets']));
            $assets = array_values(array_filter(array_map('intval', $assets)));
            if ($assets) {
                $profileQuery->whereIn('total_assets', $assets);
            }
        }

        if (!empty($where['want_kids'])) {
            $hasProfileFilter = true;
            $profileQuery->where('want_kids', (int)$where['want_kids']);
        }
        if (!empty($where['smoking'])) {
            $hasProfileFilter = true;
            $profileQuery->where('smoking', (int)$where['smoking']);
        }
        if (!empty($where['drinking'])) {
            $hasProfileFilter = true;
            $profileQuery->where('drinking', (int)$where['drinking']);
        }
        if (!empty($where['tattoo'])) {
            $hasProfileFilter = true;
            $profileQuery->where('tattoo', (int)$where['tattoo']);
        }
        if (!empty($where['only_child'])) {
            $hasProfileFilter = true;
            $profileQuery->where('only_child', (int)$where['only_child']);
        }

        if (isset($where['accept_cat']) && $where['accept_cat'] !== '') {
            $hasProfileFilter = true;
            if ((int)$where['accept_cat'] === 1) {
                $profileQuery->where(function ($q) {
                    $q->whereLike('pets', '%喜欢猫%')->whereOr('pets', 'like', '%接受养猫%');
                });
            } else {
                $profileQuery->whereLike('pets', '%讨厌猫%');
            }
        }

        if (isset($where['accept_dog']) && $where['accept_dog'] !== '') {
            $hasProfileFilter = true;
            if ((int)$where['accept_dog'] === 1) {
                $profileQuery->where(function ($q) {
                    $q->whereLike('pets', '%喜欢狗%')->whereOr('pets', 'like', '%接受养狗%');
                });
            } else {
                $profileQuery->whereLike('pets', '%讨厌狗%');
            }
        }

        if (!$hasProfileFilter) {
            return null;
        }

        return $profileQuery->column('uid');
    }

    protected function communityUserFilterParams(): array
    {
        return [
            'keyword', 'sex', 'age_min', 'age_max', 'education', 'height_min', 'height_max',
            'weight_min', 'weight_max', 'zodiac', 'school_name', 'job_title',
            'hometown_province', 'hometown_city', 'current_province', 'current_city',
            'annual_income', 'relationship_status', 'relationship_status_not', 'marital_status',
            'dating_purpose', 'car_has', 'house_has', 'total_assets', 'asset_tier',
            'want_kids', 'smoking', 'drinking', 'tattoo', 'only_child', 'accept_cat', 'accept_dog',
        ];
    }
}
