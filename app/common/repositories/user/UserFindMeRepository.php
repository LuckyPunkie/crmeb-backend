<?php

namespace app\common\repositories\user;

use app\common\model\user\UserProfile;
use app\common\repositories\system\RelevanceRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 找到我的方式：用户自选哪些信息项可被「找人」检索到（设置页「找到我的方式」，各项独立开关，类似微信「添加我的方式」）
 * 设置存 eb_user_profile.find_me_items；检索走 eb_user_find_me_index（手机号直接查 eb_user.phone，换绑后不必重建索引）
 * find_me_enabled 原为总开关，2026-10-08 起不再参与判断
 */
class UserFindMeRepository
{
    public const INDEX_TABLE = 'user_find_me_index';

    /** 可开启的信息项（顺序即资料页展示顺序） => 默认文案；实际文案跟后台字段配置走 */
    public const ITEMS = [
        'phone'          => '手机号',
        'id_card'        => '身份证号',
        'plate_number'   => '车牌号',
        'douyin_id'      => '抖音号',
        'wechat_id'      => '微信号',
        'xiaohongshu_id' => '小红书号',
        'weibo_id'       => '微博ID',
        'kuaishou_id'    => '快手号',
    ];

    /** 手机号取账号绑定的 eb_user.phone，不走后台资料字段，始终可开 */
    public const PHONE_KEY = 'phone';

    /** 账号自带来源的信息项：不看后台资料字段是否上架，始终可开、可查（身份证号来自实名认证 eb_user.card_id，资料字段下架也照样能找） */
    public const ACCOUNT_KEYS = [self::PHONE_KEY, 'id_card'];

    /**
     * 设置页用：各项（是否已填、是否开启）
     */
    public function state(int $uid, ?array $profile = null): array
    {
        if ($profile === null) {
            $profile = app()->make(UserProfileRepository::class)->getByUid($uid);
        }
        $enabledItems = self::parseItems($profile['find_me_items'] ?? '');
        $values = $this->itemValues($uid, $profile);
        $items = [];
        foreach ($this->availableItems() as $key => $title) {
            $filled = isset($values[$key]);
            $items[] = [
                'key'     => $key,
                'title'   => $title,
                'filled'  => $filled,
                'enabled' => $filled && in_array($key, $enabledItems, true),
            ];
        }
        return [
            'enabled' => !empty($enabledItems),
            'items'   => $items,
        ];
    }

    /**
     * 拨动开关即保存
     * @param string $key 信息项 field_key；all 为旧版资料页总开关，关 = 全部关闭，开 = 不变
     */
    public function toggle(int $uid, string $key, bool $enabled): array
    {
        $profileRepo = app()->make(UserProfileRepository::class);
        if ($key === 'all') {
            if (!$enabled) {
                $profileRepo->save($uid, ['find_me_items' => '']);
            }
            return $this->state($uid);
        }
        $available = $this->availableItems();
        if (!isset($available[$key])) {
            throw new ValidateException('该信息项暂不支持');
        }
        $profile = $profileRepo->getByUid($uid);
        $items = self::parseItems($profile['find_me_items'] ?? '');
        if ($enabled) {
            if (!isset($this->itemValues($uid, $profile)[$key])) {
                if ($key === self::PHONE_KEY) {
                    throw new ValidateException('请先绑定手机号');
                }
                if ($key === 'id_card' && !$this->fieldOnShelf($key)) {
                    throw new ValidateException('请先完成实名认证');
                }
                throw new ValidateException('请先填写' . $available[$key] . '并保存资料');
            }
            $items[] = $key;
            $items = self::parseItems(implode(',', $items));
        } else {
            $items = array_values(array_diff($items, [$key]));
        }
        $profileRepo->save($uid, ['find_me_items' => implode(',', $items)]);
        return $this->state($uid);
    }

    /**
     * 按当前设置和资料重建检索索引；已开启但值被清空的项顺带关掉
     * 资料保存（UserProfileRepository::save）后自动调用
     */
    public function sync(int $uid): void
    {
        $profile = app()->make(UserProfileRepository::class)->getByUid($uid);
        $items = self::parseItems($profile['find_me_items'] ?? '');
        $values = $this->itemValues($uid, $profile);
        $kept = array_values(array_filter($items, function ($key) use ($values) {
            return isset($values[$key]);
        }));
        if ($kept !== $items) {
            UserProfile::getDB()->where('uid', $uid)->update(['find_me_items' => implode(',', $kept)]);
        }
        $rows = [];
        foreach ($kept as $key) {
            if ($key === self::PHONE_KEY) {
                continue;
            }
            foreach ($values[$key] as $value) {
                $rows[] = ['uid' => $uid, 'field_key' => $key, 'value' => $value];
            }
        }
        Db::transaction(function () use ($uid, $rows) {
            Db::name(self::INDEX_TABLE)->where('uid', $uid)->delete();
            if ($rows) {
                Db::name(self::INDEX_TABLE)->insertAll($rows);
            }
        });
    }

    /**
     * 校验查询类型并规范化关键词（下单、查询共用）
     */
    public function normalizeQuery(string $key, string $keyword): string
    {
        $available = $this->availableItems();
        if (!isset($available[$key])) {
            throw new ValidateException('不支持的查询类型');
        }
        $value = self::normalize($key, $keyword);
        if ($value === '') {
            throw new ValidateException('请输入' . $available[$key]);
        }
        return $value;
    }

    /**
     * 找人：只命中开启了该项的正常用户；不返回被查的信息本身
     * 列表行带内容数、粉丝数、关注状态（is_fans=我已关注 TA，follows_me=TA 关注了我）
     */
    public function search(string $key, string $keyword, int $viewerUid = 0, int $limit = 20): array
    {
        $value = $this->normalizeQuery($key, $keyword);
        if ($key === self::PHONE_KEY) {
            $query = Db::name('user')->alias('u')
                ->join('user_profile p', 'p.uid = u.uid')
                ->where('u.phone', $value)
                ->whereRaw('FIND_IN_SET(:k, p.find_me_items)', ['k' => self::PHONE_KEY]);
        } else {
            $query = Db::name(self::INDEX_TABLE)->alias('i')
                ->join('user u', 'u.uid = i.uid')
                ->where('i.field_key', $key)
                ->where('i.value', $value);
        }
        $list = $query
            ->where('u.status', 1)
            ->whereNull('u.cancel_time')
            ->field('u.uid,u.nickname,u.avatar,u.count_content,u.count_fans')
            ->limit($limit)
            ->select()
            ->toArray();
        $uids = array_map('intval', array_column($list, 'uid'));
        $following = $followers = [];
        if ($viewerUid > 0 && $uids) {
            $type = RelevanceRepository::TYPE_COMMUNITY_FANS;
            $following = array_flip(Db::name('relevance')->where('left_id', $viewerUid)
                ->where('type', $type)->whereIn('right_id', $uids)->column('right_id'));
            $followers = array_flip(Db::name('relevance')->where('right_id', $viewerUid)
                ->where('type', $type)->whereIn('left_id', $uids)->column('left_id'));
        }
        foreach ($list as &$row) {
            $uid = (int)$row['uid'];
            $row['uid'] = $uid;
            $row['count_content'] = (int)$row['count_content'];
            $row['count_fans'] = (int)$row['count_fans'];
            $row['is_self'] = $uid === $viewerUid;
            $row['is_fans'] = isset($following[$uid]);
            $row['follows_me'] = isset($followers[$uid]);
        }
        unset($row);
        return $list;
    }

    /**
     * 比对用的规范化：手机号只留数字、去 86 前缀；证件/车牌去空格和间隔符、转大写；账号类去前导 @、不区分大小写
     */
    public static function normalize(string $key, string $value): string
    {
        $value = trim($value);
        if ($key === self::PHONE_KEY) {
            $value = (string)preg_replace('/\D+/', '', $value);
            if (strlen($value) === 13 && strpos($value, '86') === 0) {
                $value = substr($value, 2);
            }
        } elseif ($key === 'id_card' || $key === 'plate_number') {
            $value = mb_strtoupper((string)preg_replace('/[\s·.\-]+/u', '', $value));
        } else {
            $value = mb_strtolower(trim(ltrim($value, '@')));
        }
        return mb_substr($value, 0, 64);
    }

    /** 后台资料字段是否上架 */
    protected function fieldOnShelf(string $key): bool
    {
        foreach (app()->make(UserProfileFieldRepository::class)->enabledFields() as $field) {
            if ((string)$field['field_key'] === $key) {
                return true;
            }
        }
        return false;
    }

    /** 可用的信息项：账号自带的（手机号、身份证号）+ 后台已上架的资料字段；key => 文案 */
    protected function availableItems(): array
    {
        $titles = [];
        foreach (app()->make(UserProfileFieldRepository::class)->enabledFields() as $field) {
            $titles[(string)$field['field_key']] = trim((string)$field['title']);
        }
        $items = [];
        foreach (self::ITEMS as $key => $default) {
            if (in_array($key, self::ACCOUNT_KEYS, true)) {
                $items[$key] = ($titles[$key] ?? '') !== '' ? $titles[$key] : $default;
            } elseif (isset($titles[$key])) {
                $items[$key] = $titles[$key] !== '' ? $titles[$key] : $default;
            }
        }
        return $items;
    }

    /**
     * 各信息项已保存的值（规范化，空项不返回）：key => [value, ...]
     * 手机号取 eb_user.phone；身份证号同时取资料里填的和实名认证写入的 eb_user.card_id
     */
    protected function itemValues(int $uid, array $profile): array
    {
        $extra = $profile['extra_fields'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }
        $values = [];
        $user = Db::name('user')->where('uid', $uid)->field('phone,card_id')->find() ?: [];
        foreach (array_keys(self::ITEMS) as $key) {
            if ($key === self::PHONE_KEY) {
                $raw = $user['phone'] ?? '';
            } elseif ($key === 'wechat_id') {
                $raw = $profile['wechat_id'] ?? '';
            } else {
                $raw = $extra[$key] ?? '';
            }
            $list = [];
            $value = is_array($raw) ? '' : self::normalize($key, (string)$raw);
            if ($value !== '') {
                $list[] = $value;
            }
            if ($key === 'id_card') {
                $cardId = self::normalize($key, (string)($user['card_id'] ?? ''));
                if ($cardId !== '' && !in_array($cardId, $list, true)) {
                    $list[] = $cardId;
                }
            }
            if ($list) {
                $values[$key] = $list;
            }
        }
        return $values;
    }

    /** 逗号串 → 合法 key 列表（按 ITEMS 顺序、去重、去掉未知项） */
    protected static function parseItems($raw): array
    {
        $keys = array_map('trim', explode(',', (string)$raw));
        return array_values(array_intersect(array_keys(self::ITEMS), $keys));
    }
}
