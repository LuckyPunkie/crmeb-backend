<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserProfileDao as dao;
use app\common\repositories\BaseRepository;

class UserProfileRepository extends BaseRepository
{
    protected $dao;

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    public function getByUid(int $uid): array
    {
        $profile = $this->dao->getByUid($uid);
        return $profile ? $profile->toArray() : [];
    }

    public function save(int $uid, array $data): void
    {
        $allowed = [
            'height', 'weight', 'birth_month', 'zodiac',
            'education', 'education_type', 'job_title',
            'hometown_province', 'hometown_city', 'current_province', 'current_city',
            'annual_income', 'car_count', 'house_count', 'total_assets',
            'relationship_status', 'dating_purpose',
            'school_name', 'marital_status', 'want_kids', 'smoking', 'drinking',
            'tattoo', 'only_child', 'pets', 'about_me',
            'hope_age_min', 'hope_age_max', 'hope_height_min', 'hope_education',
            'hope_cities', 'hope_text', 'hobbies',
            'cover_info', 'cover_about', 'cover_hope', 'cover_hobby',
            'hobby_photo_1', 'hobby_photo_2',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (isset($filtered['hobbies']) && is_array($filtered['hobbies'])) {
            $filtered['hobbies'] = json_encode(array_values($filtered['hobbies']), JSON_UNESCAPED_UNICODE);
        }
        $this->dao->upsert($uid, $filtered);
    }

    /**
     * 批量获取用户资料简要文案：出身年份·学历·身高
     * @param array $uids
     * @return array [uid => '98年 · 本科 · 178cm', ...]
     */
    public function getBriefMapByUids(array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if (!$uids) {
            return [];
        }

        $list = \app\common\model\user\UserProfile::getDB()
            ->whereIn('uid', $uids)
            ->field('uid,height,education,birth_month')
            ->select()
            ->toArray();

        $map = [];
        foreach ($list as $row) {
            $brief = $this->formatProfileBrief($row);
            if ($brief !== '') {
                $map[(int)$row['uid']] = $brief;
            }
        }
        return $map;
    }

    /**
     * 单用户资料简要文案
     */
    public function getBriefByUid(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }
        $profile = $this->getByUid($uid);
        return $profile ? $this->formatProfileBrief($profile) : '';
    }

    /**
     * 格式：出身年份·学历·身高（缺项跳过，最多 2-3 项）
     */
    public function formatProfileBrief(array $profile): string
    {
        $parts = [];

        // 出身年份：birth_month(YYYY-MM)，格式如 98年
        $birthMonth = trim((string)($profile['birth_month'] ?? ''));
        if ($birthMonth !== '' && preg_match('/^(\d{4})/', $birthMonth, $m)) {
            $year = (int)$m[1];
            if ($year >= 1900 && $year <= (int)date('Y')) {
                $parts[] = sprintf('%02d年', $year % 100);
            }
        }

        if (!empty($profile['height'])) {
            $parts[] = ((int)$profile['height']) . 'cm';
        }

        // 学历：1高中 2大专 3本科 4硕士 5博士 6中专 7小学 8初中
        $eduMap = [
            1 => '高中', 2 => '大专', 3 => '本科', 4 => '硕士',
            5 => '博士', 6 => '中专', 7 => '小学', 8 => '初中',
        ];
        $eduKey = (int)($profile['education'] ?? 0);
        if ($eduKey && isset($eduMap[$eduKey])) {
            $parts[] = $eduMap[$eduKey];
        }

        return $parts ? implode(' · ', $parts) : '';
    }
}
