<?php

namespace app\common\repositories\merchant;

use app\common\dao\merchant\MerchantMemberDao;
use app\common\dao\merchant\MerchantMemberLevelDao;
use app\common\dao\merchant\ShoppingCreditsOrderDao;
use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\store\order\StoreOrderProductDao;
use app\common\dao\store\order\StoreRefundProductDao;
use app\common\dao\user\UserRelationDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserLabelRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 商户会员统计与客户查询。
 */
class MerchantMemberStatisticsRepository extends BaseRepository
{
    private MerchantMemberLevelDao $levelDao;
    private ShoppingCreditsOrderDao $shoppingCreditsOrderDao;
    private StoreOrderDao $storeOrderDao;
    private StoreOrderProductDao $storeOrderProductDao;
    private StoreRefundProductDao $storeRefundProductDao;
    private UserRelationDao $userRelationDao;

    public function __construct(
        MerchantMemberDao $dao,
        MerchantMemberLevelDao $levelDao,
        ShoppingCreditsOrderDao $shoppingCreditsOrderDao,
        StoreOrderDao $storeOrderDao,
        StoreOrderProductDao $storeOrderProductDao,
        StoreRefundProductDao $storeRefundProductDao,
        UserRelationDao $userRelationDao
    ) {
        $this->dao = $dao;
        $this->levelDao = $levelDao;
        $this->shoppingCreditsOrderDao = $shoppingCreditsOrderDao;
        $this->storeOrderDao = $storeOrderDao;
        $this->storeOrderProductDao = $storeOrderProductDao;
        $this->storeRefundProductDao = $storeRefundProductDao;
        $this->userRelationDao = $userRelationDao;
    }

    /**
     * 计算用户在某店铺的等级累计消费额（商品实付净额，不含运费，扣除已完成退款）。
     */
    public function consumptionAmount(int $uid, int $merId): string
    {
        $orderAmount = $this->storeOrderProductDao->query([])->alias('op')
            ->join('store_order o', 'o.order_id = op.order_id')
            ->where('o.uid', $uid)
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->where('o.is_del', 0)
            ->sum('op.product_price');

        $refundAmount = $this->storeRefundProductDao->query([])->alias('rp')
            ->join('store_refund_order ro', 'ro.refund_order_id = rp.refund_order_id')
            ->join('store_order_product op', 'op.order_product_id = rp.order_product_id')
            ->join('store_order o', 'o.order_id = op.order_id')
            ->where('o.uid', $uid)
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->where('o.is_del', 0)
            ->where('ro.status', 3)
            ->sum(Db::raw('rp.refund_price - COALESCE(rp.refund_postage, 0)'));

        return max(
            bcsub((string)($orderAmount ?: '0.00'), (string)($refundAmount ?: '0.00'), 2),
            '0.00'
        );
    }

    /**
     * 分页查询店铺会员客户列表。
     */
    public function customerList(int $merId, array $where, int $page, int $limit): array
    {
        $query = $this->dao->customerSearch(array_merge($where, ['mer_id' => $merId]))
            ->leftJoin('user_shopping_credits sc', 'sc.uid = mm.uid AND sc.mer_id = mm.mer_id')
            ->leftJoin('merchant_member_level ml', 'ml.mer_id = mm.mer_id AND ml.level = mm.level AND ml.is_del = 0');

        $count = (clone $query)->count();
        $list = $query->field([
            'u.uid', 'u.avatar', 'u.nickname', 'u.phone', 'u.sex', 'u.user_type', 'u.user_type register_type',
            'mm.id member_id', 'mm.level', 'mm.financial_status', 'mm.is_logoff', 'mm.create_time join_time',
            'ml.id level_id', 'ml.name level_name', 'ml.threshold_amount level_threshold_amount',
            'ml.discount level_discount', 'ml.benefits level_benefits', 'ml.coupon_ids level_coupon_ids',
            'um.user_merchant_id', 'um.label_id user_label_id', 'sc.recharge_amount principal', 'sc.gift_amount gift',
        ])->page($page, $limit)->order('mm.id DESC')->select()->toArray();

        $labelIds = [];
        foreach ($list as &$item) {
            $item['is_member'] = true;
            $item['user_identity'] = 'member';
            $item['freeze_status'] = $item['is_member'] && !(int)$item['financial_status'] ? 1 : 0;
            $item['principal'] = bcadd((string)($item['principal'] ?: '0.00'), '0', 2);
            $item['gift'] = bcadd((string)($item['gift'] ?: '0.00'), '0', 2);
            $item['shopping_credits_balance'] = bcadd($item['principal'], $item['gift'], 2);
            $this->formatLevelInfo($item);
            $item['label_id'] = array_values(array_unique(array_filter(array_map(
                'intval',
                explode(',', (string)($item['user_label_id'] ?? ''))
            ), fn($id) => $id > 0)));
            $labelIds = array_merge($labelIds, $item['label_id']);
        }
        unset($item);

        $labelNames = [];
        $labelIds = array_values(array_unique($labelIds));
        if ($labelIds) {
            $labelNames = app()->make(UserLabelRepository::class)->query([])
                ->where('mer_id', $merId)
                ->whereIn('label_id', $labelIds)
                ->column('label_name', 'label_id');
        }
        foreach ($list as &$item) {
            $item['label'] = [];
            foreach ($item['label_id'] as $labelId) {
                if (isset($labelNames[$labelId])) {
                    $item['label'][] = $labelNames[$labelId];
                }
            }
            unset($item['user_label_id']);
        }
        unset($item);

        return compact('count', 'list');
    }

    /**
     * 获取会员客户详情。
     */
    public function customerDetail(int $merId, int $uid): array
    {
        $user = $this->dao->customerSearch([
            'mer_id' => $merId,
            'uid' => $uid,
        ])->leftJoin('merchant_member_level ml', 'ml.mer_id = mm.mer_id AND ml.level = mm.level AND ml.is_del = 0')
            ->field([
                'u.uid', 'u.avatar', 'u.nickname', 'u.account', 'u.phone', 'u.sex', 'u.user_type',
                'mm.id member_id', 'mm.level', 'mm.financial_status', 'mm.is_logoff', 'mm.create_time join_time',
                'ml.id level_id', 'ml.name level_name', 'ml.threshold_amount level_threshold_amount',
                'ml.discount level_discount', 'ml.benefits level_benefits', 'ml.coupon_ids level_coupon_ids',
                'um.label_id user_label_id',
            ])->find();

        if (!$user) {
            throw new ValidateException('用户不存在');
        }
        $user = $user->toArray();
        $user['is_member'] = true;
        $user['user_identity'] = 'member';
        $this->formatLevelInfo($user);
        $user['label_id'] = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string)($user['user_label_id'] ?? ''))
        ), fn($id) => $id > 0)));
        $user['label'] = [];
        if ($user['label_id']) {
            $labelNames = app()->make(UserLabelRepository::class)->query([])
                ->where('mer_id', $merId)
                ->whereIn('label_id', $user['label_id'])
                ->column('label_name', 'label_id');
            foreach ($user['label_id'] as $labelId) {
                if (isset($labelNames[$labelId])) {
                    $user['label'][] = $labelNames[$labelId];
                }
            }
        }
        unset($user['user_label_id']);
        $user['freeze_status'] = !(int)$user['financial_status'] ? 1 : 0;
        $balance = app()->make(ShoppingCreditsRepository::class)->balance($uid, $merId);
        $user['principal'] = $balance['recharge_amount'];
        $user['gift'] = $balance['gift_amount'];
        $user['shopping_credits_balance'] = $balance['shopping_credits_balance'];
        $user['total_amount'] = $this->consumptionAmount($uid, $merId);
        $user['total_count'] = (int)$this->storeOrderDao->query([])
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('paid', 1)
            ->where('is_del', 0)
            ->count();
        $user['avg_amount'] = $user['total_count'] ? bcdiv($user['total_amount'], (string)$user['total_count'], 2) : '0.00';
        $user['is_follow'] = (bool)$this->userRelationDao->query([])
            ->where('uid', $uid)
            ->where('type', 10)
            ->where('type_id', $merId)
            ->count();

        return $user;
    }

    private function formatLevelInfo(array &$item): void
    {
        $item['level_name'] = (string)($item['level_name'] ?? '');
        $item['level_info'] = [
            'id' => (int)($item['level_id'] ?? 0),
            'level' => (int)($item['level'] ?? 0),
            'name' => $item['level_name'],
            'threshold_amount' => (string)($item['level_threshold_amount'] ?? '0.00'),
            'discount' => (string)($item['level_discount'] ?? '100.00'),
            'benefits' => $this->parseIds($item['level_benefits'] ?? ''),
            'coupon_ids' => $this->parseIds($item['level_coupon_ids'] ?? ''),
        ];
        unset($item['level_id'], $item['level_threshold_amount'], $item['level_discount'], $item['level_benefits'], $item['level_coupon_ids']);
    }

    private function parseIds($value): array
    {
        return $value === '' || $value === null
            ? []
            : array_values(array_filter(array_map('intval', explode(',', (string)$value))));
    }

    /**
     * 统计店铺会员基础指标。
     */
    public function baseInfo(int $merId, string $date = ''): array
    {
        $date = $date ?: 'lately7';
        $start = date('Y-m-d 00:00:00', strtotime(getStartModelTime($date)));
        $end = date('Y-m-d 23:59:59');
        [$compareStart, $compareEnd] = $this->previousDateRange($start, $end);
        $currentMembers = $this->dao->search()->where('mer_id', $merId)->where('is_logoff', 0)->count();
        $newMembersQuery = $this->dao->search()->where('mer_id', $merId);
        getModelTime($newMembersQuery, $date, 'create_time');
        $newMembers = $newMembersQuery->count();
        $compareNewMembersQuery = $this->dao->search()->where('mer_id', $merId);
        getModelTime($compareNewMembersQuery, $this->modelTimeRange($compareStart, $compareEnd), 'create_time');
        $compareNewMembers = $compareNewMembersQuery->count();

        $rechargeQuery = $this->shoppingCreditsOrderDao->query([])->where('mer_id', $merId)->where('paid', 1);
        $rechargeAmountQuery = clone $rechargeQuery;
        getModelTime($rechargeAmountQuery, $date, 'pay_time');
        $rechargeAmount = $rechargeAmountQuery->sum('recharge_amount');
        $rechargeOrdersQuery = clone $rechargeQuery;
        getModelTime($rechargeOrdersQuery, $date, 'pay_time');
        $rechargeOrders = $rechargeOrdersQuery->count();
        $compareRechargeAmountQuery = clone $rechargeQuery;
        getModelTime($compareRechargeAmountQuery, $this->modelTimeRange($compareStart, $compareEnd), 'pay_time');
        $compareRechargeAmount = $compareRechargeAmountQuery->sum('recharge_amount');
        $compareRechargeOrdersQuery = clone $rechargeQuery;
        getModelTime($compareRechargeOrdersQuery, $this->modelTimeRange($compareStart, $compareEnd), 'pay_time');
        $compareRechargeOrders = $compareRechargeOrdersQuery->count();

        $shoppingCreditsPayType = array_search(ShoppingCreditsRepository::PAY_TYPE, StoreOrderRepository::PAY_TYPE, true);
        $consumeQuery = $this->storeOrderDao->query([])->alias('o')
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->where('o.is_del', 0)
            ->where('o.pay_type', $shoppingCreditsPayType);
        $consumeAmountQuery = clone $consumeQuery;
        getModelTime($consumeAmountQuery, $date, 'o.create_time');
        $consumeAmount = $consumeAmountQuery->sum('o.pay_price');
        $consumeOrdersQuery = clone $consumeQuery;
        getModelTime($consumeOrdersQuery, $date, 'o.create_time');
        $consumeOrders = $consumeOrdersQuery->count();
        $compareConsumeQuery = $this->storeOrderDao->query([])->alias('o')
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->where('o.is_del', 0)
            ->where('o.pay_type', $shoppingCreditsPayType);
        $compareConsumeAmountQuery = clone $compareConsumeQuery;
        getModelTime($compareConsumeAmountQuery, $this->modelTimeRange($compareStart, $compareEnd), 'o.create_time');
        $compareConsumeAmount = $compareConsumeAmountQuery->sum('o.pay_price');
        $compareConsumeOrdersQuery = clone $compareConsumeQuery;
        getModelTime($compareConsumeOrdersQuery, $this->modelTimeRange($compareStart, $compareEnd), 'o.create_time');
        $compareConsumeOrders = $compareConsumeOrdersQuery->count();

        return [
            'base_info' => [
                ['title' => '现有会员数', 'count' => $currentMembers, 'statistic' => '0.00', 'is_cumulative' => true],
                ['title' => '新增会员数', 'count' => $newMembers, 'statistic' => $this->growthRate($newMembers, $compareNewMembers)],
                ['title' => '充值金额（元）', 'count' => (float)$rechargeAmount, 'statistic' => $this->growthRate($rechargeAmount, $compareRechargeAmount)],
                ['title' => '充值订单数', 'count' => $rechargeOrders, 'statistic' => $this->growthRate($rechargeOrders, $compareRechargeOrders)],
                ['title' => '会员消费金额（元）', 'count' => (float)$consumeAmount, 'statistic' => $this->growthRate($consumeAmount, $compareConsumeAmount)],
                ['title' => '会员消费订单数', 'count' => $consumeOrders, 'statistic' => $this->growthRate($consumeOrders, $compareConsumeOrders)],
            ],
        ];
    }

    /**
     * 统计店铺会员等级分布。
     */
    public function levelData(int $merId, string $date = ''): array
    {
        $memberQuery = $this->dao->search()
            ->where('mer_id', $merId)
            ->where('is_logoff', 0);
        if ($date !== '') {
            getModelTime($memberQuery, $date, 'create_time');
        }
        $memberRows = $memberQuery
            ->field('level,COUNT(id) value')
            ->group('level')
            ->select()
            ->toArray();
        $levelCounts = [];
        foreach ($memberRows as $row) {
            $levelCounts[(int)$row['level']] = (int)$row['value'];
        }

        $levels = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->field('name,level')
            ->order('level ASC')
            ->select()
            ->toArray();
        foreach ($levels as &$level) {
            $level['value'] = $levelCounts[(int)$level['level']] ?? 0;
        }
        unset($level);

        return $levels;
    }

    /**
     * 统计店铺会员来源分布。
     */
    public function channelData(int $merId, string $date = ''): array
    {
        $query = $this->dao->query([])->alias('mm')
            ->join('user u', 'u.uid = mm.uid')
            ->where('mm.mer_id', $merId)
            ->where('mm.is_logoff', 0);
        if ($date !== '') {
            getModelTime($query, $date, 'mm.create_time');
        }
        $list = $query
            ->group('u.user_type')
            ->field('u.user_type name,COUNT(mm.id) value')
            ->select()
            ->toArray();
        if (empty($list)) {
            $list = [
                ['name' => 'h5','value' => 0 ],
                ['name' => 'wechat','value' => 0 ],
            ];
        }
        return $list;
    }

    /**
     * 统计店铺会员和充值趋势。
     */
    public function memberTrend(int $merId, string $date = ''): array
    {
        $date = $date ?: 'lately7';
        $startDate = getStartModelTime($date);
        $endDate = $this->trendEndDate($date);
        // 会员概览页的“本年”会传完整年度范围，接口直传时也可能使用 year。
        $normalizedDate = str_replace('/', '-', $date);
        $isYear = $date === 'year'
            || (bool)preg_match('/^(\d{4})-01-01-(\d{4})-12-31$/', $normalizedDate, $yearMatches)
            && $yearMatches[1] === $yearMatches[2];
        $groupFormat = $isYear ? '%Y-%m' : '%Y-%m-%d';
        $newMemberQuery = $this->dao->search()->where('mer_id', $merId);
        getModelTime($newMemberQuery, $date, 'create_time');
        $newMemberRows = $newMemberQuery
            ->field(Db::raw("from_unixtime(unix_timestamp(create_time),'{$groupFormat}') as date, COUNT(*) as new_members"))
            ->group('date')
            ->select()
            ->toArray();
        $newMembers = [];
        foreach ($newMemberRows as $row) {
            $newMembers[$row['date']] = (int)$row['new_members'];
        }

        $rechargeUserQuery = $this->shoppingCreditsOrderDao->search([
            'mer_id' => $merId,
            'paid' => 1,
        ]);
        getModelTime($rechargeUserQuery, $date, 'pay_time');
        $rechargeUserRows = $rechargeUserQuery
            ->field(Db::raw("from_unixtime(unix_timestamp(pay_time),'{$groupFormat}') as date, COUNT(DISTINCT uid) as recharge_users"))
            ->group('date')
            ->select()
            ->toArray();
        $rechargeUsers = [];
        foreach ($rechargeUserRows as $row) {
            $rechargeUsers[$row['date']] = (int)$row['recharge_users'];
        }

        $memberTrend = [];
        if ($isYear) {
            // 年度按月生成完整时间轴，查询没有数据的月份也返回 0。
            $period = date('Y-m-01', strtotime($startDate));
            $lastPeriod = date('Y-m-01', strtotime($endDate));
            while (strtotime($period) <= strtotime($lastPeriod)) {
                $month = date('Y-m', strtotime($period));
                $memberTrend[] = [
                    'date' => date('m', strtotime($period)),
                    'new_members' => $newMembers[$month] ?? 0,
                    'recharge_users' => $rechargeUsers[$month] ?? 0,
                ];
                $period = date('Y-m-d', strtotime($period . ' +1 month'));
            }
        } else {
            foreach (getDatesBetweenTwoDays($startDate, $endDate, 'Y-m-d') as $day) {
                $memberTrend[] = [
                    'date' => date('m-d', strtotime($day)),
                    'new_members' => $newMembers[$day] ?? 0,
                    'recharge_users' => $rechargeUsers[$day] ?? 0,
                ];
            }
        }

        return ['member_trend' => $memberTrend];
    }

    /**
     * 统计店铺购物金消费趋势。
     */
    public function consumeTrend(int $merId, string $date = ''): array
    {
        $date = $date ?: 'lately7';
        $shoppingCreditsPayType = array_search(ShoppingCreditsRepository::PAY_TYPE, StoreOrderRepository::PAY_TYPE, true);
        $startDate = getStartModelTime($date);
        $endDate = $this->trendEndDate($date);
        // 会员概览页的“本年”会传完整年度范围，接口直传时也可能使用 year。
        $normalizedDate = str_replace('/', '-', $date);
        $isYear = $date === 'year'
            || (bool)preg_match('/^(\d{4})-01-01-(\d{4})-12-31$/', $normalizedDate, $yearMatches)
            && $yearMatches[1] === $yearMatches[2];
        $groupFormat = $isYear ? '%Y-%m' : '%Y-%m-%d';
        $consumeQuery = $this->storeOrderDao->query([])->alias('o')
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->where('o.is_del', 0)
            ->where('o.pay_type', $shoppingCreditsPayType);
        getModelTime($consumeQuery, $date, 'o.create_time');
        $consumeRows = $consumeQuery
            ->field(Db::raw("from_unixtime(unix_timestamp(o.create_time),'{$groupFormat}') as date, COUNT(DISTINCT o.uid) as user_count, COUNT(*) as order_count, SUM(o.pay_price) as amount"))
            ->group('date')
            ->select()
            ->toArray();
        $consumeData = [];
        foreach ($consumeRows as $row) {
            $consumeData[$row['date']] = [
                'user_count' => (int)$row['user_count'],
                'order_count' => (int)$row['order_count'],
                'amount' => (string)($row['amount'] ?: '0.00'),
            ];
        }

        $consumeTrend = [];
        if ($isYear) {
            // 年度按月生成完整时间轴，查询没有数据的月份也返回 0。
            $period = date('Y-m-01', strtotime($startDate));
            $lastPeriod = date('Y-m-01', strtotime($endDate));
            while (strtotime($period) <= strtotime($lastPeriod)) {
                $month = date('Y-m', strtotime($period));
                $monthData = $consumeData[$month] ?? [];
                $consumeTrend[] = [
                    'date' => date('m', strtotime($period)),
                    'user_count' => $monthData['user_count'] ?? 0,
                    'order_count' => $monthData['order_count'] ?? 0,
                    'amount' => $monthData['amount'] ?? '0.00',
                ];
                $period = date('Y-m-d', strtotime($period . ' +1 month'));
            }
        } else {
            foreach (getDatesBetweenTwoDays($startDate, $endDate, 'Y-m-d') as $day) {
                $dayData = $consumeData[$day] ?? [];
                $consumeTrend[] = [
                    'date' => date('m-d', strtotime($day)),
                    'user_count' => $dayData['user_count'] ?? 0,
                    'order_count' => $dayData['order_count'] ?? 0,
                    'amount' => $dayData['amount'] ?? '0.00',
                ];
            }
        }

        return ['consume_trend' => $consumeTrend];
    }

    private function modelTimeRange(string $start, string $end): string
    {
        return date('Y/m/d', strtotime($start)) . '-' . date('Y/m/d', strtotime($end));
    }

    private function trendEndDate(string $date): string
    {
        if (preg_match('/(?:-|~|至|,)\s*(\d{4}[\/-]\d{2}[\/-]\d{2})$/', $date, $matches)) {
            return date('Y-m-d', strtotime(str_replace('/', '-', $matches[1])));
        }
        return date('Y-m-d');
    }

    private function previousDateRange(string $start, string $end): array
    {
        $startDay = strtotime(date('Y-m-d', strtotime($start)));
        $endDay = strtotime(date('Y-m-d', strtotime($end)));
        $days = max((int)(($endDay - $startDay) / 86400) + 1, 1);
        return [
            date('Y-m-d 00:00:00', strtotime("-{$days} days", $startDay)),
            date('Y-m-d 23:59:59', strtotime('-1 day', $startDay)),
        ];
    }

    private function growthRate($current, $previous): string
    {
        $current = (float)$current;
        $previous = (float)$previous;
        if ($previous == 0.0) {
            return $current == 0.0 ? '0.00' : '100.00';
        }
        return number_format((($current - $previous) / $previous) * 100, 2, '.', '');
    }

}
