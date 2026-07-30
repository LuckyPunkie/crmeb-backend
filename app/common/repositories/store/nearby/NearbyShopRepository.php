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

namespace app\common\repositories\store\nearby;

use app\common\dao\system\merchant\MerchantDao;
use app\common\dao\store\nearby\NearbyShopCategoryDao;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantType;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 附近好店 Repository
 * 底层数据源为 eb_merchant 表
 * 展示条件：nearby_is_show=1 且 nearby_category_id>0（商户端已完成附近好店设置）
 */
class NearbyShopRepository extends BaseRepository
{
    /**
     * @var MerchantDao
     */
    protected $dao;

    /**
     * @var NearbyShopCategoryDao
     */
    protected $categoryDao;

    public function __construct(MerchantDao $dao, NearbyShopCategoryDao $categoryDao)
    {
        $this->dao = $dao;
        $this->categoryDao = $categoryDao;
    }

    /**
     * 获取附近好店列表（瀑布流）
     * 支持：分类/标签/关键词/营业中/距离排序
     */
    public function getList(array $where, int $page, int $limit)
    {
        $limit = min($limit, 100); // 分页上限保护

        // 仅展示：开关开启 + 已在商户端完成附近好店设置（至少选了分类）
        $query = Merchant::getDB()->alias('m')
            ->where('m.nearby_is_show', 1)
            ->where('m.nearby_category_id', '>', 0)
            ->where('m.status', 1)
            ->where('m.is_del', 0);

        // 店铺类型：默认线下/实体店，逛网店时传 online
        $storeType = $where['store_type'] ?? 'offline';
        $typeIds = $this->resolveStoreTypeIds($storeType);
        if ($typeIds === []) {
            return ['count' => 0, 'list' => []];
        }
        $query->whereIn('m.type_id', $typeIds);

        // 分类筛选
        if (!empty($where['nearby_category_id'])) {
            $query->where('m.nearby_category_id', (int)$where['nearby_category_id']);
        }

        // 关键词搜索
        if (!empty($where['keyword'])) {
            $keyword = '%' . $where['keyword'] . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('m.mer_name', 'like', $keyword)
                    ->whereOr('m.mer_keyword', 'like', $keyword)
                    ->whereOr('m.mer_info', 'like', $keyword);
            });
        }

        // 标签筛选（多个标签逗号分隔）
        if (!empty($where['tags'])) {
            $tags = explode(',', $where['tags']);
            foreach ($tags as $tag) {
                $query->where('m.nearby_tags', 'like', '%' . trim($tag) . '%');
            }
        }

        // 营业中筛选 — 用 SQL 时间区间比较替代 LIKE 匹配
        if (!empty($where['is_open'])) {
            $now = date('H:i');
            $query->where(function ($q) use ($now) {
                $q->where('m.nearby_business_hours', 'IS NULL')
                ->whereOr('m.nearby_business_hours', '')
                ->whereOrRaw(
                    "IF(
                        SUBSTRING_INDEX(m.nearby_business_hours, '-', 1) <= SUBSTRING_INDEX(m.nearby_business_hours, '-', -1),
                        TIME(?) BETWEEN TIME(SUBSTRING_INDEX(m.nearby_business_hours, '-', 1)) AND TIME(SUBSTRING_INDEX(m.nearby_business_hours, '-', -1)),
                        TIME(?) >= TIME(SUBSTRING_INDEX(m.nearby_business_hours, '-', 1)) OR TIME(?) <= TIME(SUBSTRING_INDEX(m.nearby_business_hours, '-', -1))
                    )",
                    [$now, $now, $now]
                );
            });
        }

        // 排序
        $order = $where['order'] ?? 'smart';
        switch ($order) {
            case 'distance':
                if (!empty($where['latitude']) && !empty($where['longitude'])) {
                    $lat = (float)$where['latitude'];
                    $lng = (float)$where['longitude'];
                    $query->fieldRaw("*, 
                        ROUND(6371 * acos(cos(radians(?)) * cos(radians(nearby_latitude)) 
                        * cos(radians(nearby_longitude) - radians(?)) 
                        + sin(radians(?)) * sin(radians(nearby_latitude))), 2) as distance",
                        [$lat, $lng, $lat])
                        ->order('distance', 'ASC');
                } else {
                    $query->order('m.nearby_sort', 'DESC');
                }
                break;
            case 'rating':
                $query->order('m.product_score', 'DESC');
                break;
            case 'hot':
                $query->order('m.care_count', 'DESC')
                    ->order('m.sales', 'DESC');
                break;
            case 'price_asc':
                $query->order('m.nearby_avg_price', 'ASC');
                break;
            case 'price_desc':
                $query->order('m.nearby_avg_price', 'DESC');
                break;
            case 'smart':
            default:
                $query->order('m.nearby_sort', 'DESC')
                    ->order('m.product_score', 'DESC')
                    ->order('m.care_count', 'DESC');
                break;
        }

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        // 批量预取分类名称，避免 N+1 查询
        $catIds = array_filter(array_unique(array_column($list->toArray(), 'nearby_category_id')));
        $categories = [];
        if (!empty($catIds)) {
            $rows = \app\common\model\store\nearby\NearbyShopCategory::getDB()
                ->whereIn('id', $catIds)
                ->select()
                ->toArray();
            // ThinkPHP Collection 无 keyBy，用 array_column 按 id 索引
            $categories = array_column($rows, null, 'id');
        }

        // 格式化列表数据
        $formattedList = [];
        foreach ($list as $item) {
            $formattedList[] = $this->formatListItem($item, $where, $categories);
        }

        return ['count' => $count, 'list' => $formattedList];
    }

    /**
     * 获取商家详情
     */
    public function getDetail(int $merId, array $where = [])
    {
        $merchant = Merchant::getInstance()
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->where('is_del', 0)
            ->where('nearby_is_show', 1)
            ->where('nearby_category_id', '>', 0)
            ->find();

        if (!$merchant) {
            return null;
        }

        $data = $merchant->toArray();
        $data = $this->formatDetailItem($data, $where);

        // 距离计算（如有经纬度）
        if (!empty($where['latitude']) && !empty($where['longitude'])
            && !empty($data['nearby_latitude']) && !empty($data['nearby_longitude'])) {
            $data['distance'] = $this->haversine(
                (float)$where['latitude'], (float)$where['longitude'],
                (float)$data['nearby_latitude'], (float)$data['nearby_longitude']
            );
        } else {
            $data['distance'] = 0;
        }

        return $data;
    }

    /**
     * 格式化详情数据
     */
    protected function formatDetailItem(array $data, array $where = [])
    {
        // 标签
        $data['tags'] = !empty($data['nearby_tags']) ? explode(',', $data['nearby_tags']) : [];

        // 分类名称 / 是否餐饮店（父级或自身名为「餐饮美食」）
        $data['nearby_category_name'] = '';
        $data['nearby_category_pid'] = 0;
        $data['is_catering'] = 0;
        if (!empty($data['nearby_category_id'])) {
            $category = $this->categoryDao->get($data['nearby_category_id']);
            if ($category) {
                $data['nearby_category_name'] = $category['name'] ?? '';
                $data['nearby_category_pid'] = (int)($category['pid'] ?? 0);
                $parentName = '';
                if ($data['nearby_category_pid'] > 0) {
                    $parent = $this->categoryDao->get($data['nearby_category_pid']);
                    $parentName = $parent ? ($parent['name'] ?? '') : '';
                } else {
                    $parentName = $data['nearby_category_name'];
                }
                $data['is_catering'] = (mb_strpos($parentName, '餐饮') !== false || mb_strpos($data['nearby_category_name'], '餐饮') !== false) ? 1 : 0;
            }
        }

        // 是否营业中
        $data['is_open'] = $this->checkIsOpen($data['nearby_business_hours'] ?? '');

        // 微信号
        $data['wechat'] = $data['nearby_wechat'] ?? '';

        // 商家公告
        $data['announcement'] = $data['nearby_announcement'] ?? '';

        // 评分星数（转换为1-5的星级格式，保留真实0分）
        $data['star'] = round($data['product_score'] ?? 5, 1);

        // 评价数
        $data['reply_count'] = $data['care_count'] ?? 0;

        // 人均消费
        $data['avg_price'] = $data['nearby_avg_price'] ?? 0;

        // 推荐菜（通过RecommendRepository获取）
        try {
            $recommendRepo = app()->make(\app\common\repositories\store\nearby\NearbyShopRecommendRepository::class);
            $data['recommends'] = $recommendRepo->getTopList($data['mer_id'], 6)->toArray();
        } catch (\think\db\exception\DbException $e) {
            \think\facade\Log::warning('NearbyShop getDetail recommends failed: ' . $e->getMessage());
            $data['recommends'] = [];
        }

        // 套餐（通过PackageRepository获取）
        try {
            $packageRepo = app()->make(\app\common\repositories\store\nearby\NearbyShopPackageRepository::class);
            $data['packages'] = $packageRepo->getTopList($data['mer_id'], 4)->toArray();
        } catch (\think\db\exception\DbException $e) {
            \think\facade\Log::warning('NearbyShop getDetail packages failed: ' . $e->getMessage());
            $data['packages'] = [];
        }

        // 优惠券（匹配本店有效的平台/店铺券，单次查询供 coupons 与 vouchers 共用）
        try {
            $now = date('Y-m-d H:i:s');
            $couponList = \app\common\model\store\coupon\StoreCoupon::getDB()
                ->where('mer_id', $data['mer_id'])
                ->where('status', 1)
                ->where('is_del', 0)
                ->where(function ($query) use ($now) {
                    // 未设时间限制 or 在有效期内
                    $query->where('is_timeout', 0)
                        ->whereOr(function ($q) use ($now) {
                            $q->where('is_timeout', 1)
                              ->where('start_time', '<', $now)
                              ->where('end_time', '>', $now);
                        });
                })
                ->order('coupon_price DESC')
                ->select()
                ->toArray();
            $data['coupons'] = $couponList;
            $data['vouchers'] = $couponList;
        } catch (\Exception $e) {
            \think\facade\Log::warning('NearbyShop getDetail coupons failed: ' . $e->getMessage());
            $data['coupons'] = [];
            $data['vouchers'] = [];
        }

        // 排名标签
        $data['ranking'] = !empty($data['nearby_category_name']) ? $data['nearby_category_name'] . '热门' : '';

        // 评价列表（关联 product_reply 表查询最近评价）
        try {
            $replyRepo = app()->make(\app\common\repositories\store\product\ProductReplyRepository::class);
            $replies = $replyRepo->getList(['mer_id' => $data['mer_id']], 1, 10);
            $data['replies'] = $replies['list'] ?? [];
        } catch (\Exception $e) {
            \think\facade\Log::warning('NearbyShop getDetail replies failed: ' . $e->getMessage());
            $data['replies'] = [];
        }

        // 企业微信顾客群（一期 branch_id=0 总店；分店上线后按当前门店 ID 查询）
        try {
            $branchId = (int)($data['branch_id'] ?? $where['branch_id'] ?? 0);
            $weworkRepo = app()->make(\app\common\repositories\system\merchant\MerchantWeworkGroupRepository::class);
            $data['wework'] = $weworkRepo->toApiPayload(
                $weworkRepo->getByMerBranch((int)$data['mer_id'], $branchId)
            );
        } catch (\Throwable $e) {
            \think\facade\Log::warning('NearbyShop getDetail wework failed: ' . $e->getMessage());
            $data['wework'] = ['has_group' => false];
        }

        return $data;
    }

    /**
     * 格式化列表项
     * @param object $item 商家数据行
     * @param array $where 查询条件（含经纬度）
     * @param array $categories 批量预取的分类缓存 [id => [...]]
     */
    protected function formatListItem($item, array $where = [], array $categories = [])
    {
        $data = is_array($item) ? $item : $item->toArray();

        // 标签
        $data['tags'] = !empty($data['nearby_tags']) ? explode(',', $data['nearby_tags']) : [];

        // 分类名称（从预取缓存获取，避免 N+1 查询）
        $data['nearby_category_name'] = '';
        if (!empty($data['nearby_category_id']) && isset($categories[$data['nearby_category_id']])) {
            $data['nearby_category_name'] = $categories[$data['nearby_category_id']]['name'] ?? '';
        }

        // 是否营业中
        $data['is_open'] = $this->checkIsOpen($data['nearby_business_hours'] ?? '');

        // 微信号
        $data['wechat'] = $data['nearby_wechat'] ?? '';

        // 距离计算（保留2位小数，与SQL Haversine精度一致）
        if (!empty($where['latitude']) && !empty($where['longitude'])
            && !empty($data['nearby_latitude']) && !empty($data['nearby_longitude'])) {
            $data['distance'] = $this->haversine(
                (float)$where['latitude'], (float)$where['longitude'],
                (float)$data['nearby_latitude'], (float)$data['nearby_longitude']
            );
        } else {
            $data['distance'] = 0;
        }

        // 评分星数
        $data['star'] = round($data['product_score'] ?? 5, 1);

        // 评价数
        $data['reply_count'] = $data['care_count'] ?? 0;

        // 人均消费
        $data['avg_price'] = $data['nearby_avg_price'] ?? 0;

        return $data;
    }

    /**
     * Haversine 公式计算两点间距离（公里）
     * 返回保留2位小数
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);
        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos($lat1) * cos($lat2) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * 检查是否在营业时间内
     * 支持跨夜营业时间 如 "22:00-02:00"
     */
    protected function checkIsOpen(string $businessHours): bool
    {
        if (empty($businessHours)) return false;
        $parts = explode('-', $businessHours);
        if (count($parts) !== 2) return false;

        $start = trim($parts[0]);
        $end = trim($parts[1]);
        if (empty($start) || empty($end)) return false;

        $now = date('H:i');

        // 跨夜：start > end → 用 OR 条件
        if ($start > $end) {
            return ($now >= $start || $now <= $end);
        }
        return ($now >= $start && $now <= $end);
    }

    /**
     * 按店铺类型名称解析 type_id
     * online → 线上店；offline（默认）→ 实体店/线下店
     *
     * @return int[]
     */
    protected function resolveStoreTypeIds(string $storeType): array
    {
        $query = MerchantType::getDB();
        if ($storeType === 'online') {
            $query->where('type_name', 'like', '%线上%');
        } else {
            $query->where(function ($q) {
                $q->where('type_name', 'like', '%实体%')
                    ->whereOr('type_name', 'like', '%线下%');
            });
        }

        return array_map('intval', $query->column('mer_type_id') ?: []);
    }
}
