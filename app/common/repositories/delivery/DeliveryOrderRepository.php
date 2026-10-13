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
namespace app\common\repositories\delivery;

use app\common\dao\delivery\DeliveryOrderDao;
use app\common\model\delivery\DeliveryService;
use app\common\model\delivery\DeliveryStation;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\MiniOrderConfirmJob;
use crmeb\services\DeliverySevices;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Route;

/**
 * 同城配送订单
 */
class DeliveryOrderRepository extends BaseRepository
{

    public const MANAGE_STATUS_ALL = 'all';
    public const MANAGE_STATUS_PENDING_DISPATCH = 'pending_dispatch';
    public const MANAGE_STATUS_PENDING_ACCEPT = 'pending_accept';
    public const MANAGE_STATUS_PICKING_UP = 'picking_up';
    public const MANAGE_STATUS_DELIVERING = 'delivering';
    public const MANAGE_STATUS_PENDING_REVIEW = 'pending_review';
    public const MANAGE_STATUS_COMPLETED = 'completed';
    public const MANAGE_STATUS_REFUNDED = 'refunded';
    public const MANAGE_STATUS_DELETED = 'deleted';

    public const MANAGE_STATUS_TEXT = [
        self::MANAGE_STATUS_ALL => '全部',
        self::MANAGE_STATUS_PENDING_DISPATCH => '待指派',
        self::MANAGE_STATUS_PENDING_ACCEPT => '待骑手接单',
        self::MANAGE_STATUS_PICKING_UP => '赶往提货点',
        self::MANAGE_STATUS_DELIVERING => '送货中',
        self::MANAGE_STATUS_PENDING_REVIEW => '待评价',
        self::MANAGE_STATUS_COMPLETED => '交易完成',
        self::MANAGE_STATUS_REFUNDED => '已退款',
        self::MANAGE_STATUS_DELETED => '已删除',
    ];

    protected $statusData = [
        0 => '待确认',
        1 => '待到店',
        2 => '待取货',
        3 => '配送中',
        4 => '已完成',
        -1 => '已取消',
        9 => '物品返回中',
        10 => '物品返回完成',
        100 => '骑士到店',
    ];

    protected $message = [
        2 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_WAITING,
        3 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_ING,
        4 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_OVER,
        -1 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_CANCEL,
        9 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_REFUNDING,
        10 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_REFUND,
        100 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_ARRIVE,
    ];

    public function __construct(DeliveryOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  商户的同城配送订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     */
    public function merList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where)->with(
            [
                'station',
                'storeOrder' => function ($query) {
                    $query->with(['take' => function ($query) {
                        $query->field('station_name,station_id');
                    }]);
                }
            ])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 平台同城配送订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     */
    public function sysList(array $where, int $page, int $limit)
    {
        $query = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'station',
            'storeOrder.orderProduct',

        ])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 平台、商户后台配送订单管理列表。
     */
    public function manageList(array $where, int $page, int $limit, bool $isPlatform, int $merId = 0): array
    {
        $status = (string)($where['status'] ?? self::MANAGE_STATUS_ALL) ?: self::MANAGE_STATUS_ALL;
        unset($where['status']);

        $query = $this->getManageOrderQuery($where, $isPlatform, $merId);
        $this->applyManageStatus($query, $status);
        $count = (clone $query)->count();
        $list = $query
            ->field('StoreOrder.*')
            ->with([
                'take',
                'orderProduct',
                'merchant' => function ($query) {
                    $query->field('mer_id,mer_name,mer_avatar');
                },
                'user' => function ($query) {
                    $query->field('uid,nickname,avatar,phone');
                },
                'deliveryOrder' => function ($query) {
                    $query->with(['deliveryService' => function ($query) {
                        $query->field('service_id,name,phone,avatar');
                    }]);
                },
            ])
            ->page($page, $limit)
            ->order('create_time DESC,order_id DESC')
            ->select();

        foreach ($list as $order) {
            $manageStatus = $this->getOrderManageStatus($order);
            $order->delivery_manage_status = $manageStatus;
            $order->delivery_manage_status_text = self::MANAGE_STATUS_TEXT[$manageStatus];
        }

        return compact('count', 'list');
    }

    /**
     * 配送订单管理头部状态统计，统计条件与列表一致。
     */
    public function manageTitle(array $where, bool $isPlatform, int $merId = 0): array
    {
        unset($where['status']);
        $query = $this->getManageOrderQuery($where, $isPlatform, $merId);
        $row = $query->field(Db::raw(
            'COUNT(StoreOrder.order_id) AS all_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND StoreOrder.status = 0 AND dmo.delivery_order_id IS NULL THEN 1 ELSE 0 END), 0) AS pending_dispatch_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND dmo.status = 0 THEN 1 ELSE 0 END), 0) AS pending_accept_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND dmo.status IN (1, 2) THEN 1 ELSE 0 END), 0) AS picking_up_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND dmo.status = 3 THEN 1 ELSE 0 END), 0) AS delivering_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND StoreOrder.status = 2 THEN 1 ELSE 0 END), 0) AS pending_review_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND StoreOrder.status = 3 THEN 1 ELSE 0 END), 0) AS completed_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 0 AND StoreOrder.status = -1 THEN 1 ELSE 0 END), 0) AS refunded_count,' .
            'COALESCE(SUM(CASE WHEN StoreOrder.is_del = 1 THEN 1 ELSE 0 END), 0) AS deleted_count'
        ))->find();
        $counts = $row ? $row->toArray() : [];
        $statistics = [];
        foreach (self::MANAGE_STATUS_TEXT as $status => $title) {
            $statistics[] = [
                'status' => $status,
                'title' => $title,
                'count' => (int)($counts[$status . '_count'] ?? 0),
            ];
        }
        return $statistics;
    }

    /**
     * 批量派单。每个订单独立执行并返回失败明细。
     */
    public function batchDispatch(array $orderIds, int $serviceId, bool $isPlatform, int $merId = 0): array
    {
        if ($serviceId <= 0) {
            throw new ValidateException('请选择配送人员');
        }
        $orderIds = $this->normalizeBatchOrderIds($orderIds);
        $successOrderIds = [];
        $failures = [];
        foreach ($orderIds as $orderId) {
            try {
                if ($isPlatform) {
                    $this->sysDispatch($orderId, ['service_id' => $serviceId]);
                } else {
                    $this->merDispatch($orderId, $merId, ['service_id' => $serviceId]);
                }
                $successOrderIds[] = $orderId;
            } catch (\Throwable $e) {
                $failures[] = ['order_id' => $orderId, 'message' => $e->getMessage()];
            }
        }
        return [
            'success_count' => count($successOrderIds),
            'fail_count' => count($failures),
            'success_order_ids' => $successOrderIds,
            'failures' => $failures,
        ];
    }

    /**
     * 批量确认送达。每个订单独立执行并返回失败明细。
     */
    public function batchConfirm(array $orderIds, bool $isPlatform, int $merId = 0): array
    {
        $orderIds = $this->normalizeBatchOrderIds($orderIds);
        $successOrderIds = [];
        $failures = [];
        foreach ($orderIds as $orderId) {
            try {
                if ($isPlatform) {
                    $this->sysConfirm($orderId);
                } else {
                    $this->confirm($orderId, $merId);
                }
                $successOrderIds[] = $orderId;
            } catch (\Throwable $e) {
                $failures[] = ['order_id' => $orderId, 'message' => $e->getMessage()];
            }
        }
        return [
            'success_count' => count($successOrderIds),
            'fail_count' => count($failures),
            'success_order_ids' => $successOrderIds,
            'failures' => $failures,
        ];
    }

    protected function getManageOrderQuery(array $where, bool $isPlatform, int $merId)
    {
        $where['paid'] = 1;
        $where['is_city_delivery'] = $isPlatform ? DeliveryStation::TYPE_PLATFORM : 0;
        if (!$isPlatform) {
            $where['mer_id'] = $merId;
        }
        $query = app()->make(StoreOrderRepository::class)
            ->search($where, $isPlatform ? null : 0)
            ->where('StoreOrder.order_type', 2)
            ->leftJoin('DeliveryOrder dmo', 'dmo.order_id = StoreOrder.order_id');
        return $query;
    }

    protected function applyManageStatus($query, string $status): void
    {
        if (!isset(self::MANAGE_STATUS_TEXT[$status])) {
            throw new ValidateException('配送订单状态参数错误');
        }
        switch ($status) {
            case self::MANAGE_STATUS_PENDING_DISPATCH:
                $query->where('StoreOrder.is_del', 0)
                    ->where('StoreOrder.status', 0)
                    ->whereNull('dmo.delivery_order_id');
                break;
            case self::MANAGE_STATUS_PENDING_ACCEPT:
                $query->where('StoreOrder.is_del', 0)->where('dmo.status', 0);
                break;
            case self::MANAGE_STATUS_PICKING_UP:
                $query->where('StoreOrder.is_del', 0)->whereIn('dmo.status', [1, 2]);
                break;
            case self::MANAGE_STATUS_DELIVERING:
                $query->where('StoreOrder.is_del', 0)->where('dmo.status', 3);
                break;
            case self::MANAGE_STATUS_PENDING_REVIEW:
                $query->where('StoreOrder.is_del', 0)->where('StoreOrder.status', 2);
                break;
            case self::MANAGE_STATUS_COMPLETED:
                $query->where('StoreOrder.is_del', 0)->where('StoreOrder.status', 3);
                break;
            case self::MANAGE_STATUS_REFUNDED:
                $query->where('StoreOrder.is_del', 0)->where('StoreOrder.status', -1);
                break;
            case self::MANAGE_STATUS_DELETED:
                $query->where('StoreOrder.is_del', 1);
                break;
        }
    }

    protected function getOrderManageStatus($order): string
    {
        if ((int)$order->is_del === 1) {
            return self::MANAGE_STATUS_DELETED;
        }
        if ((int)$order->status === -1) {
            return self::MANAGE_STATUS_REFUNDED;
        }
        if ((int)$order->status === 3) {
            return self::MANAGE_STATUS_COMPLETED;
        }
        if ((int)$order->status === 2) {
            return self::MANAGE_STATUS_PENDING_REVIEW;
        }
        if (!$order->deliveryOrder) {
            return self::MANAGE_STATUS_PENDING_DISPATCH;
        }
        $deliveryStatus = (int)$order->deliveryOrder->status;
        if ($deliveryStatus === 0) {
            return self::MANAGE_STATUS_PENDING_ACCEPT;
        }
        if (in_array($deliveryStatus, [1, 2], true)) {
            return self::MANAGE_STATUS_PICKING_UP;
        }
        if ($deliveryStatus === 3) {
            return self::MANAGE_STATUS_DELIVERING;
        }
        return self::MANAGE_STATUS_PENDING_REVIEW;
    }

    protected function normalizeBatchOrderIds(array $orderIds): array
    {
        if (!$orderIds) {
            throw new ValidateException('请选择订单');
        }
        $ids = [];
        foreach ($orderIds as $orderId) {
            if (filter_var($orderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new ValidateException('订单ID格式错误');
            }
            $ids[] = (int)$orderId;
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 100) {
            throw new ValidateException('单次最多操作100个订单');
        }
        return $ids;
    }

    /**
     * 获取平台配送开单、配送费及配送员提成趋势
     * @param string $startDate 旧版开始日期，格式为 YYYY-MM-DD
     * @param string $endDate 旧版结束日期，格式为 YYYY-MM-DD
     * @param string $userSearchType 用户搜索类型
     * @param string $userSearch 用户搜索内容
     * @param string $date 日期筛选，支持快捷日期或日期范围
     * @param string $deliveryName 配送员姓名
     * @param string $deliveryUid 配送员用户UID
     * @param string $deliveryPhone 配送员手机号
     * @param string $deliveryKeyword 配送员关键词
     * @return array 按日期跨度分组并补零后的趋势数据
     */
    public function getPlatformTrend(
        string $startDate = '',
        string $endDate = '',
        string $userSearchType = 'all',
        string $userSearch = '',
        string $date = '',
        string $deliveryName = '',
        string $deliveryUid = '',
        string $deliveryPhone = '',
        string $deliveryKeyword = ''
    ): array
    {
        if (!in_array($userSearchType, ['all', 'uid', 'phone', 'name'], true)) {
            throw new ValidateException('用户搜索类型错误');
        }
        $deliveryName = trim($deliveryName);
        $deliveryUid = trim($deliveryUid);
        $deliveryPhone = trim($deliveryPhone);
        $deliveryKeyword = trim($deliveryKeyword);
        $date = $this->normalizePlatformTrendDate($date, $startDate, $endDate);
        [$dates, $customFormat] = getStepLength($date);
        $formatMap = [
            'lately7' => '%m-%d',
            'lately30' => '%m-%d',
            'month' => '%d',
            'year' => '%m',
        ];
        $groupFormat = $customFormat ?: ($formatMap[$date] ?? '%m-%d');

        $query = StoreOrder::getDB()->alias('so');
        getModelTime($query, $date, 'so.pay_time');
        if ($userSearch !== '') {
            $query->leftJoin('user u', 'u.uid = so.uid');
            $search = '%' . $userSearch . '%';
            if ($userSearchType === 'uid') {
                if (!ctype_digit($userSearch)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('so.uid', (int)$userSearch);
                }
            } elseif ($userSearchType === 'phone') {
                $query->whereLike('u.phone|so.user_phone', $search);
            } elseif ($userSearchType === 'name') {
                $query->whereLike('u.nickname|u.real_name|so.real_name', $search);
            } else {
                $query->whereLike(
                    'so.uid|u.phone|u.nickname|u.real_name|so.user_phone|so.real_name',
                    $search
                );
            }
        }
        if ($deliveryName !== '' || $deliveryUid !== '' || $deliveryPhone !== '' || $deliveryKeyword !== '') {
            $query->join('delivery_order do', 'do.order_id = so.order_id')
                ->join('delivery_service ds', 'ds.service_id = do.service_id')
                ->where('ds.type', DeliveryService::TYPE_PLATFORM)
                ->where('ds.mer_id', 0);
            if ($deliveryName !== '') {
                $query->whereLike('ds.name', '%' . $deliveryName . '%');
            }
            if ($deliveryPhone !== '') {
                $query->whereLike('ds.phone', '%' . $deliveryPhone . '%');
            }
            if ($deliveryUid !== '') {
                if (!ctype_digit($deliveryUid)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('ds.uid', (int)$deliveryUid);
                }
            }
            if ($deliveryKeyword !== '') {
                $query->whereLike('ds.service_id|ds.name|ds.phone', '%' . $deliveryKeyword . '%');
            }
        }

        $rows = $query
            ->where('so.city_delivery_type', DeliveryStation::TYPE_PLATFORM)
            ->where('so.paid', 1)
            ->where('so.is_del', 0)
            ->field(Db::raw(
                "DATE_FORMAT(so.pay_time, '{$groupFormat}') AS date," .
                'COUNT(DISTINCT so.order_id) AS order_count,' .
                'COALESCE(SUM(so.delivery_settlement_fee), 0) AS delivery_amount,' .
                'COALESCE(SUM(so.delivery_service_fee), 0) AS commission_amount'
            ))
            ->group('date')
            ->order('date ASC')
            ->select()
            ->toArray();

        $rowMap = array_column($rows, null, 'date');
        $result = [
            'date' => [],
            'order_count' => [],
            'delivery_amount' => [],
            'commission_amount' => [],
        ];
        foreach ($dates as $dateLabel) {
            $row = $rowMap[$dateLabel] ?? [];
            $result['date'][] = str_replace('-', '.', $dateLabel);
            $result['order_count'][] = (int)($row['order_count'] ?? 0);
            $result['delivery_amount'][] = bcadd((string)($row['delivery_amount'] ?? 0), '0', 2);
            $result['commission_amount'][] = bcadd((string)($row['commission_amount'] ?? 0), '0', 2);
        }

        return $result;
    }

    /**
     * 规范平台配送趋势日期参数：date 优先，未传时兼容旧版起止日期参数
     * @param string $date 日期筛选，支持 lately7、lately30、month、year 或日期范围
     * @param string $startDate 旧版开始日期，格式为 YYYY-MM-DD
     * @param string $endDate 旧版结束日期，格式为 YYYY-MM-DD
     * @return string 可供统计日期工具使用的日期筛选值
     */
    protected function normalizePlatformTrendDate(string $date, string $startDate, string $endDate): string
    {
        if ($date === '') {
            if (($startDate === '') !== ($endDate === '')) {
                throw new ValidateException('开始日期和结束日期需同时填写');
            }
            if ($startDate === '') {
                return 'lately30';
            }
            if (!$this->isValidTrendDate($startDate, 'Y-m-d') || !$this->isValidTrendDate($endDate, 'Y-m-d')) {
                throw new ValidateException('日期格式错误，请使用YYYY-MM-DD格式');
            }
            if (strtotime($startDate) > strtotime($endDate)) {
                throw new ValidateException('开始日期不能晚于结束日期');
            }
            if ((int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1 > 60) {
                throw new ValidateException('最多查询60天数据');
            }
            return str_replace('-', '/', $startDate) . '-' . str_replace('-', '/', $endDate);
        }

        if (in_array($date, ['lately7', 'lately30', 'month', 'year'], true)) {
            return $date;
        }
        if (preg_match('/^(\d{4}[\/-]\d{2}[\/-]\d{2})-(\d{4}[\/-]\d{2}[\/-]\d{2})$/', $date, $matches) !== 1) {
            throw new ValidateException('日期格式错误，请使用YYYY/MM/DD-YYYY/MM/DD格式');
        }

        $normalizedStartDate = str_replace('-', '/', $matches[1]);
        $normalizedEndDate = str_replace('-', '/', $matches[2]);
        if (!$this->isValidTrendDate($normalizedStartDate, 'Y/m/d') || !$this->isValidTrendDate($normalizedEndDate, 'Y/m/d')) {
            throw new ValidateException('日期格式错误，请使用YYYY/MM/DD-YYYY/MM/DD格式');
        }
        if (strtotime($normalizedStartDate) > strtotime($normalizedEndDate)) {
            throw new ValidateException('开始日期不能晚于结束日期');
        }
        return $normalizedStartDate . '-' . $normalizedEndDate;
    }

    /**
     * 校验趋势统计中的单个日期是否符合指定格式且为真实日期
     * @param string $date 待校验日期
     * @param string $format 日期格式
     * @return bool 日期有效时返回 true，否则返回 false
     */
    protected function isValidTrendDate(string $date, string $format): bool
    {
        $timestamp = strtotime($date . ' 00:00:00');
        return $timestamp !== false && date($format, $timestamp) === $date;
    }

    /**
     * 需要配置的订单详情
     * @param int $id
     * @param int|null $merId
     * @return \app\common\model\BaseModel|array|mixed|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function detail(int $id, ?int $merId)
    {
        $where[$this->dao->getPk()] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $res = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'station',
        ])->find();
         // 先检查结果是否存在
        if (!$res) throw new ValidateException('订单不存在');

        if (in_array((int)$res['station_type'], [0, DeliveryStation::TYPE_PLATFORM], true)) {
            $res['data'] = [
                'order_code' => $res['order_code'],
                'to_address' => $res['to_address'],
                'from_address' => $res['from_address'],
                'state' => $this->statusData[$res['status']] ?? '',
                'note' => $res['mark'],
                'order_price' => $res['fee'],
                'distance' => $res['distance'],
            ];
            return $res;
        }

         // 第三方配送订单查询第三方详情
        try {
            $order = DeliverySevices::create($res['station_type'])->getOrderDetail($res);
            $res['data'] = [
                'order_code' => $order['order_code'],
                'to_address' => $order['to_address'],
                'from_address' => $order['from_address'],
                'state' => $order['state'],
                'note' => $order['note'],
                'order_price' => $order['order_price'],
                'distance' => round(($order['distance'] / 1000), 2) . ' km',
            ];
        } catch (\Exception $e) {
            // 记录异常并返回友好提示
            Log::error('获取配送订单详情失败：' . $e->getMessage());
            throw new ValidateException('获取订单详情失败，请稍后再试');
        }
        return $res;
    }

    /**
     * 取消配送订单
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     */
    public function cancelForm($id, $merId)
    {
        $formData = $this->dao->get($id);
        if (!$formData) throw new ValidateException('订单不存在');
        if ($formData['status'] == -1) throw new ValidateException('订单已取消，无法操作');

        $form = Elm::createForm(Route::buildUrl('merchantStoreDeliveryOrderCancel', ['id' => $id])->build());
        $rule = [];
        if ($formData['station_type'] == DeliverySevices::DELIVERY_TYPE_DADA) {
            $options = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->reasons();
            $rule[] = Elm::select('reason', '取消原因：')->placeholder('请选择取消原因')->options($options);
            $rule[] = Elm::text('cancel_reason', '其他原因说明：')->placeholder('请输入其它原因说明');
        }
        if (in_array($formData['station_type'], [DeliverySevices::DELIVERY_TYPE_UU, 0])) {
            $rule[] = Elm::input('reason', '取消原因：')->placeholder('请输入取消原因')->required('请输入取消原因');
        }
        $form->setRule($rule);
        return $form->setTitle('取消同城配送订单', $formData);
    }

    /**
     *  取消表单
     * @param $id
     * @param $merId
     * @param $reason
     * @return mixed
     * @author Qinii
     */
    public function cancel($id, $merId, $reason)
    {
        $order = $this->dao->getWhere([$this->dao->getPk() => $id, 'mer_id' => $merId]);
        if (!$order) throw new ValidateException('配送订单不存在');
        if ($order['status'] == -1) throw new ValidateException('请勿重复操作');
        // if ($order['status'] == 4) throw new ValidateException('该订单已完成，无法取消');
        $data = [
            'origin_id' => $order['order_sn'],
            'order_code' => $order['order_code'],
            'reason' => is_array($reason) ? $reason['reason'] : $reason,
        ];
        return Db::transaction(function () use ($order, $data, $merId) {

            if(in_array($order['station_type'],[DeliverySevices::DELIVERY_TYPE_DADA, DeliverySevices::DELIVERY_TYPE_UU])) {
                if ($order['station_type'] == DeliverySevices::DELIVERY_TYPE_DADA) {
                    $options = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->reasons();
                    $data['reason'] = $options[9]['value'];
                    $data['cancel_reason'] = $options[9]['label'];
                }
                DeliverySevices::create($order['station_type'], $merId)->cancelOrder($data);
            }else{
                $order->status = -1;
                $order->reason = $data['reason'];
                $order->save();
            }

            // 订单取消后操作
            $this->cancelAfter($order);
            //订单记录
            $statusRepository = app()->make(StoreOrderStatusRepository::class);
            $orderStatus = [
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => '同城配送订单已取消',
                'change_type' => $statusRepository::ORDER_DELIVERY_CITY_CANCEL,
            ];
            $statusRepository->createAdminLog($orderStatus);
        });
    }

    /**
     * 订单取消后操作
     * @param $deliveryOrder
     * @param $deductFee
     * @param $mark
     * @return void
     * @author Qinii
     */
    public function cancelAfter($deliveryOrder)
    {
        //修改配送订单
        // $deliveryOrder->status = -1;
        // $deliveryOrder->reason = $mark;
        // $deliveryOrder->deduct_fee = $deductFee;
        // $deliveryOrder->save();

        //修改商城订单
        $res = app()->make(StoreOrderRepository::class)->get($deliveryOrder['order_id']);
        if($res->status == 1) {
            $res->status = 0;
            if ($deliveryOrder['station_type']) {
                $merchantTakeInfo = $res['merchant_take_info'];
                $merchantTakeInfo[$res['mer_id']]['sync_status'] = -1;
                $merchantTakeInfo[$res['mer_id']]['sync_desc'] = '后台手动取消';
                $res['merchant_take_info'] = json_encode($merchantTakeInfo);
            }
            $res->save();
        }

        //修改商户
        // $merchant = app()->make(MerchantRepository::class)->get($deliveryOrder['mer_id']);
        // $balance = bcadd(bcsub($deliveryOrder['fee'], $deductFee, 2), $merchant->delivery_balance, 2);
        // $merchant->delivery_balance = $balance;
        // $merchant->save();
    }


    /**
     *  同城配送订单信息回调
     * @param $data
     * @author Qinii
     * @day 2/17/22
     */
    public function notify($data)
    {
        //达达
        /**
         * 订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10, 骑士到店=100,创建达达运单失败=1000 可参考文末的状态说明）
         */
        Log::info('同城回调参数：' . var_export(['=======', $data, '======='], 1));
        if (isset($data['data'])) {
            $data = json_decode($data['data'], 1);
        }
        if(isset($data['biz'])) {
            $data = json_decode($data['biz'], 1);
        }

        $reason = '';
        $deductFee = 0;
        $delivery = [];
        if (isset($data['order_status'])) {
            $order_sn = $data['order_id'];
            if ($data['order_status'] == 1) {
                $orderData = $this->dao->getSearch(['sn' => $data['order_id']])->find();
                if (!$orderData['finish_code']) {
                    $orderData->finish_code = $data['finish_code'];
                    $orderData->save();
                }
                return;
            } else if (in_array($data['order_status'], [2, 3, 4, 5, 9, 10, 100])) {
                $status = $data['order_status'];
                if ($data['order_status'] == 5) {
                    $msg = [
                        '取消：',
                        '达达配送员取消：',
                        '商家主动取消：',
                        '系统或客服取消：',
                    ];
                    //1:达达配送员取消；2:商家主动取消；3:系统或客服取消；0:默认值
                    $status = -1;
                    $reason = $msg[$data['cancel_from']] . $data['cancel_reason'];
                }
                $deductFee = $data['deductFee'] ?? 0;
                if (isset($data['dm_name']) && $data['dm_name']) {
                    $delivery = [
                        'delivery_name' => $data['dm_name'],
                        'delivery_id' => $data['dm_mobile'],
                    ];
                }

            }
        } else if (isset($data['state'])) {  //uu
            $deliveryOrder = $this->dao->getWhere(['order_code' => $data['orderCode']]);
            $order_sn = $deliveryOrder['order_sn'];
            //当前状态 1下单成功 2跑男取消（订单状态回退到1） 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消 -2客服取消（订单状态-1） -3超时无人接单取消（订单状态-1）
            switch ($data['state']) {
                case 2:
                    $deliveryOrder->status = 0;
                    $deliveryOrder->reason = '跑男取消';
                    $deliveryOrder->save();

                    break;
                case 3:
                    $status = 2;
                    break;
                case 4:
                    $status = 100;
                    break;
                case 5:
                    $status = 3;
                    break;
                case 6:
                    $status = 3;
                    break;
                case 10:
                    $status = 4;
                    break;
                case -1:
                    $status = -1;
                    $reason = '商家主动取消';
                    break;
                case -2:
                    $status = -1;
                    $reason = '客服取消';
                    break;
                case -3:
                    $status = -1;
                    $reason = '超时无人接单取消';
                    break;
                default:
                    break;
            }
            if (isset($data['driverName']) && $data['driverName']) {
                $delivery = [
                    'delivery_name' => $data['driverName'],
                    'delivery_id' => $data['driverMobile'],
                ];
            }
        }

        if (isset($order_sn) && isset($status)) {
            $res = $this->dao->getWhere(['order_sn' => $order_sn]);
            if ($res) {
                $this->notifyAfter($status, $reason, $res, $delivery, $deductFee);
            } else {
                Log::info('同城配送回调，未查询到订单：' . $order_sn);
            }
        }
    }

    /**
     * 同城配送回调
     * @param $status
     * @param $reason
     * @param $res
     * @param $data
     * @param $deductFee
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function notifyAfter($status, $reason, $res, $data, $deductFee)
    {
        if (!isset($this->statusData[$status])) return;

        $make = app()->make(StoreOrderRepository::class);
        $orderData = $make->get($res['order_id']);

        $now = date('Y-m-d H:i:s');
        return Db::transaction(function () use ($res, $data, $status, $reason, $deductFee, $orderData, $make, $now) {
            $res->status = $status;
            $res->reason = $reason;
            $res->deduct_fee = $deductFee;
            if ((int)$status === 3 && empty($res->pickup_time)) {
                $res->pickup_time = $now;
            }
            if ((int)$status === 4 && empty($res->confirm_time)) {
                $res->confirm_time = $now;
            }
            $res->save();
            //订单记录
            $statusRepository = app()->make(StoreOrderStatusRepository::class);
            $message = '订单同城配送【' . $this->statusData[$status] . '】';
            $orderStatus = [
                'order_id' => $orderData['order_id'],
                'order_sn' => $orderData['order_sn'],
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => $message,
                'change_type' => $this->message[$status],
            ];
            $statusRepository->createSysLog($orderStatus);
            if ($status == 2 && !empty($data))
                $make->update($res['order_id'], $data);
            if ($status == 4) {
                $order = $make->get($res['order_id']);
                $user = app()->make(UserRepository::class)->get($order['uid']);
                $make->update($res['order_id'], ['status' => 2, 'verify_time' => $now]);
                $make->takeAfter($order, $user);
            }
            // if ($status == -1) {
            //     $this->cancelAfter($res, $deductFee, $reason);
            // }
        });
    }

    /**
     * 创建配送订单
     * @param $id
     * @param $merId
     * @param $data
     * @param $order
     * @return bool
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function create($id, $merId, $data, $order)
    {
        $type = $order->take->type;
        $where = ['station_id' => $order['merchant_take_id'], 'mer_id' => $merId, 'status' => 1, 'type' => $type];
        $station = app()->make(DeliveryStationRepository::class)->getWhere($where);

        if (!$station) {
            Log::info('同城配送创建订单，门店信息不存在');
            return false;
        }
        if (!$station['city_name']) {
            Log::info('同城配送创建订单，门店缺少所在城市，请重新编辑门店信息');
            return false;
        }
        //地址转经纬度
        $addres = lbs_address([], $order['user_address']);
        $location = $addres['location'];
        // UU坐标转换为百度坐标
        if ($type == DeliverySevices::DELIVERY_TYPE_UU) {
            [$station['lng'], $station['lat']] = gcj02ToBd09($station['lng'], $station['lat']);
            [$location['lng'], $location['lat']] = gcj02ToBd09($addres['location']['lng'], $addres['location']['lat']);
        }

        try {
            $service = DeliverySevices::create($type, $merId);
            $orderSn = $this->getOrderSn();
            $callbackUrl = rtrim(systemConfig('site_url'), '/') . '/api/notice/callback';
            $weight = array_sum(array_column(array_column(array_column($data, 'cart_info'), 'productAttr'), 'weight'));

            $priceParams = $this->getPriceParams($station, $order, $location, $type, $merId);
            $priceParams['cargo_weight'] = max((int)$weight, 1);
            $priceParams['shop_no'] = $station['origin_shop_id'];
            $priceParams['origin_id'] = $orderSn;
            $priceParams['callback_url'] = $callbackUrl;
            $priceParams['receiver_lat'] = $addres['location']['lat'];
            $priceParams['receiver_lng'] = $addres['location']['lng'];
            //计算价格
            $priceData = $service->getOrderPrice($priceParams);
            if ($type == DeliverySevices::DELIVERY_TYPE_UU) {
                $priceData['shop_no'] = $station['origin_shop_id'];
                $priceData['receiver'] = $order['real_name'];
                $priceData['receiver_phone'] = $order['user_phone'];
                $priceData['note'] = $order['mark'];
                $priceData['callback_url'] = $callbackUrl;
                $priceData['push_type'] = "OPEN_ORDER"; // 测试订单:TEST_ORDER 开放订单:OPEN_ORDER
                $priceData['special_type'] = 'NOT_NEED_WARM'; // NEED_WARM
            }
            //发布订单
            $res = $service->addOrder($priceData);

            $ret = [
                'station_id' => $order['merchant_take_id'],
                'order_sn' => $orderSn,
                'city_code' => $station['city_name'],
                'receiver_phone' => $order['user_phone'],
                'user_name' => $order['real_name'],
                'from_address' => $station['station_address'],
                'to_address' => $order['user_address'],
                'order_code' => $type == 2 ? $res['orderCode'] : $priceData['deliveryNo'],
                'order_id' => $id,
                'mer_id' => $merId,
                'status' => $res['status'] ?? 0,
                'station_type' => $type,
                'to_lat' => $addres['location']['lat'],
                'to_lng' => $addres['location']['lng'],
                'from_lat' => $station['lat'],
                'from_lng' => $station['lng'],
                'distance' => $priceData['distance'],
                'fee' => $type == 2 ? bcdiv($priceData['needPayMoney'], 100, 2) : $priceData['fee'],
                'mark' => $order['mark'],
                'uid' => $order['uid'],
                'reason' => ''
            ];
            //入库操作
            $this->dao->create($ret);

            return true;
        } catch (\Exception $e) {
            $merchantTakeInfo = $order['merchant_take_info'] ?? [];
            $merchantTakeInfo[$merId]['sync_status'] = -1;
            $merchantTakeInfo[$merId]['sync_desc'] = $e->getMessage();

            $order['merchant_take_info'] = json_encode($merchantTakeInfo);
            $order->save();

            return false;
        }
    }

    /**
     * 获取订单价格参数
     * @param DeliveryStation $deliveryStation
     * @param StoreOrder $order
     * @param array $addres
     * @param int $type
     * @return array
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function getPriceParams(DeliveryStation $deliveryStation, StoreOrder $order, array $addres, int $type, $merId)
    {
        $data = [];
        $type = (int)$type;
        switch ($type) {
            case 1:
                $city = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->getCity([]);
                $res = [];
                foreach ($city as $item) {
                    $res[$item['label']] = $item['key'];
                }
                $data = [
                    'city_code' => $res[$deliveryStation['city_name']],
                    'cargo_price' => $order['pay_price'],
                    'is_prepay' => 0,
                    'receiver_name' => $order['real_name'],
                    'receiver_address' => $order['user_address'],
                    'cargo_weight' => 0,
                    'receiver_phone' => $order['user_phone'],
                    'is_finish_code_needed' => 1,
                ];
                break;
            case 2:
                $data = [
                    'from_address' => $deliveryStation['station_address'],
                    'to_address' => $order['user_address'],
                    'city_name' => $deliveryStation['city_name'],
                    'goods_type' => $deliveryStation['business']['label'],
                    'send_type' => '0',
                    'to_lat' => $addres['lat'],
                    'to_lng' => $addres['lng'],
                    'from_lat' => $deliveryStation['lat'],
                    'from_lng' => $deliveryStation['lng'],
                ];
                break;
        }
        return $data;
    }

    public function getTitle()
    {
        $query = app()->make(MerchantRepository::class)->getSearch(['is_del' => 0]);
        $merchant = $query->count();
        $price = app()->make(ServeOrderRepository::class)
            ->getSearch(['type' => 10, 'status' => 1])->sum('pay_price');
        $balance = $query->sum('delivery_balance');
        return [
            [
                'className' => 'el-icon-s-order',
                'count' => $merchant,
                'field' => '个',
                'name' => '商户数'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $price,
                'field' => '元',
                'name' => '商户充值总金额'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $balance,
                'field' => '元',
                'name' => '商户当前余额'
            ],
        ];
    }

    /**
     * 销毁订单。
     * 本函数用于根据给定的订单ID和商家ID删除订单。首先，它会验证订单是否存在，如果不存在，则抛出一个异常；
     * 如果存在，则执行删除操作。
     *
     * @param int $id 订单ID。这是主键，用于唯一标识一个订单。
     * @param int $merId 商家ID。用于确定订单所属的商家。
     * @return bool 返回删除操作的结果。如果删除成功，返回true；否则，返回false。
     * @throws ValidateException 如果订单不存在，则抛出此异常。
     */
    public function destory($id, $merId)
    {
        // 根据订单ID和商家ID构建查询条件
        $where = [
            $this->dao->getPk() => $id, // 使用主键作为查询条件之一
            'mer_id' => $merId, // 商家ID作为查询条件之一
        ];

        // 根据查询条件查找订单
        $res = $this->dao->getSearch($where)->find();

        // 如果查询结果为空，即订单不存在，则抛出异常
        if (!$res) throw new ValidateException('订单不存在');

        // 删除订单，并返回删除结果
        return $this->dao->delete($id);
    }

    /**
     * 生成订单编号
     *
     * 本函数用于生成唯一的订单编号。编号由时间戳和随机数组成，确保了编号的唯一性和可追踪性。
     * 时间戳部分精确到毫秒，增加了编号的唯一性，随机数部分进一步确保了即使在毫秒级别内有多个订单生成，也能得到不同的编号。
     *
     * @return string 返回生成的订单编号
     */
    public function getOrderSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并格式化为整数，用于订单编号的时间戳部分
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单编号：前缀 + 毫秒时间戳 + 随机数
        // 使用'dc'作为前缀，表示订单编号的来源或类型
        // 随机数生成范围确保大于毫秒时间戳转换的整数，避免重复，并且预设了一个最小值98369，以保持编号的一定长度和特征
        $orderId = 'dc' . $msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369));

        return $orderId;
    }


    /**
     * 根据订单ID和用户ID查询订单信息
     *
     * 本函数用于通过给定的订单ID和用户ID，从数据库中检索相应的订单信息。
     * 如果找不到匹配的订单，将抛出一个验证异常，指出订单不存在。
     * 这是对前端接口的封装，确保了只有合法的订单才能被查询和访问。
     *
     * @param int $id 订单ID
     * @param int $uid 用户ID
     * @return array 查询到的订单信息
     * @throws ValidateException 如果订单不存在则抛出异常
     */
    public function show(int $id, int $uid)
    {
        // 构建查询条件
        $where['order_id'] = $id;
        $where['uid'] = $uid;

        // 执行查询，同时加载订单状态和订单详情
        $res = $this->dao->getSearch($where)->with(['storeOrderStatus', 'storeOrder'])->find();

        // 如果查询结果为空，则抛出异常提示订单不存在
        if (!$res) throw new ValidateException('订单不存在');

        // 返回查询结果
        return $res;
    }

    const ENABLE_ASSIGNED = [
        'RECEIVE' => 0, // 领取
        'DISPATCH' => 1, // 指派
    ];
    const DELIVERY_TYPE = [
        0 => '商家配送',
        1 => '达达配送',
        2 => 'UU配送',
        3 => '平台配送',
    ];
    /**
     * 配送员领取
     *
     * @param int $merId
     * @param int $orderId
     * @param int $serviceId
     * @return void
     */
    public function selfReceive(int $merId, int $orderId, int $serviceId)
    {
        // 检查订单是否存在
        $order = $this->orderInfo($orderId, $merId, 0);
        if ((int)$order['delivery_grab_status'] !== 1) {
            throw new ValidateException('当前订单不支持配送员抢单');
        }
        $stationType = (int)$order['city_delivery_type'];
        if ($stationType === DeliveryStation::TYPE_PLATFORM) {
            $this->checkPlatformDeliveryStatus();
            if ((int)systemConfig('platform_delivery_grab_status') !== 1) {
                throw new ValidateException('平台未开启配送员抢单，无法接单');
            }
        } else if (!merchantConfig($merId, 'mer_delivery_order_status')) {
            throw new ValidateException('商家未开启配送员抢单，无法接单');
        }

        $params['service_id'] = $serviceId;
        return $this->dispatch(
            $order,
            $merId,
            $params,
            self::ENABLE_ASSIGNED['RECEIVE'],
            0,
            $stationType === DeliveryStation::TYPE_PLATFORM ? DeliveryStation::TYPE_PLATFORM : 0,
            $serviceId
        );
    }
    /**
     * 商家指派/管理端指派
     *
     * @param integer $orderId
     * @param integer $merId
     * @param array $params
     * @param integer $serviceLogId
     * @return void
     */
    public function merDispatch(int $orderId, int $merId, array $params, int $serviceLogId = 0)
    {
        // 检查订单是否存在
        $order = $this->orderInfo($orderId, $merId, 0);
        return $this->dispatch($order, $merId, $params, self::ENABLE_ASSIGNED['DISPATCH'], $serviceLogId);
    }

    /**
     * 平台指派配送员
     * @param int $orderId
     * @param array $params
     * @return bool
     */
    public function sysDispatch(int $orderId, array $params)
    {
        $this->checkPlatformDeliveryStatus();
        $order = $this->platformOrderInfo($orderId, 0);
        if ($order->deliveryOrder) {
            throw new ValidateException('订单已派单，请勿重复操作');
        }

        return $this->dispatch(
            $order,
            (int)$order['mer_id'],
            $params,
            self::ENABLE_ASSIGNED['DISPATCH'],
            0,
            DeliveryStation::TYPE_PLATFORM
        );
    }
    /**
     * 同城配送派单
     *
     * @param $order
     * @param int $merId 商家ID
     * @param array $params 派单参数
     * @return bool 派单结果
     */
    public function dispatch($order, int $merId, array $params, int $enableAssigned, int $serviceLogId = 0, int $stationType = 0, int $deliveryServiceId = 0)
    {
        // 检查配送站是否存在
        $station = app()->make(DeliveryStationRepository::class)->deliveryStationInfo($order['merchant_take_id'], $merId);
        if ((int)$station['type'] !== $stationType) {
            $deliveryType = self::DELIVERY_TYPE[$station['type']] ?? '未知配送';
            if ($stationType === 0 && (int)$station['type'] === DeliveryStation::TYPE_PLATFORM) {
                throw new ValidateException('平台配送订单由平台操作，商户无法派单');
            }
            if ($stationType === DeliveryStation::TYPE_PLATFORM) {
                throw new ValidateException("订单配送方式不是平台配送，当前配送方式为【{$deliveryType}】");
            }
            throw new ValidateException("用户所选自提点不支持商家派单，请返回列表同步第三方配送订单。当前配送方式为【{$deliveryType}】");
        }
        $this->checkDeliveryService((int)$params['service_id'], $stationType, $merId);
        $deliveryOrderStatus = 3;
        if ($stationType === DeliveryStation::TYPE_PLATFORM) {
            $currentStatus = $enableAssigned === self::ENABLE_ASSIGNED['RECEIVE'] ? 0 : null;
            $deliveryOrderStatus = $this->getNextPlatformDeliveryStatus($currentStatus);
        }
        [$settlementFee, $serviceFee] = $this->getServiceFee(
            $station,
            $order,
            $stationType === DeliveryStation::TYPE_PLATFORM ? 0 : $merId
        );

        // 数据处理
        $ret = [
            'station_id' => $order['merchant_take_id'],
            'order_sn' => $this->getOrderSn(),
            'city_code' => $station['city_name'],
            'receiver_phone' => $order['user_phone'],
            'user_name' => $order['real_name'],
            'from_address' => $station['station_address'],
            'to_address' => $order['user_address'],
            'order_code' => '',
            'order_id' => $order['order_id'],
            'mer_id' => $merId,
            'status' => $deliveryOrderStatus,
            'station_type' => $stationType,
            'distance' => '',
            'fee' => $order['pay_postage'],
            'settlement_fee' => $settlementFee,
            'mark' => $order['mark'],
            'uid' => $order['uid'],
            'reason' => '',
            'service_id' => $params['service_id'],
            'service_fee' => $serviceFee,
        ];
        // 进入配送中的派单没有后续取货动作，直接记录派单时的取货时间。
        if ((int)$deliveryOrderStatus === 3) {
            $ret['pickup_time'] = date('Y-m-d H:i:s');
        }
        //入库操作
        try {
            Db::transaction(function () use ($ret, $order, $enableAssigned, $serviceLogId, $deliveryServiceId) {
                // 创建配送订单
                $this->dao->create($ret);
                // 更新订单状态
                $order->enable_assigned = $enableAssigned;
                $order->delivery_type = 5;
                $order->status = 1;
                $order->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已派单',
                    'change_type' => StoreOrderStatusRepository::DELIVERY_ORDER_DISPATCH,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($deliveryServiceId) {
                    $storeOrderStatusRepository->createDeliveryLog($deliveryServiceId, $orderStatus);
                } elseif ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 商家改派/管理端改派
     *
     * @param integer $orderId
     * @param integer $merId
     * @param array $params
     * @param integer $serviceLogId
     * @return void
     */
    public function merUpdateDispatch(int $orderId, int $merId, array $params, int $serviceLogId = 0)
    {
        // 检查订单是否存在， 1 表示配送中
        $order = $this->orderInfo($orderId, $merId, 1);
        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送订单由平台操作，商户无法改派');
        }
        // 检查配送站是否存在
        $station = app()->make(DeliveryStationRepository::class)->deliveryStationInfo($order['merchant_take_id'], $merId);
        if($station['type']) {
            $deliveryType = self::DELIVERY_TYPE[$station['type']] ?? '未知配送';
            throw new ValidateException('配送方式错误无法派单,请检查配送设置。当前设置配送方式为'.$deliveryType);
        }

        return $this->updateDispatch($order, $params, $serviceLogId);
    }

    /**
     * 平台改派配送员
     * @param int $orderId
     * @param array $params
     * @return bool
     */
    public function sysUpdateDispatch(int $orderId, array $params)
    {
        $this->checkPlatformDeliveryStatus();
        $order = $this->platformOrderInfo($orderId, 1);
        if (!$order->deliveryOrder || (int)$order->deliveryOrder->station_type !== DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送记录不存在，无法改派');
        }
        $params['status'] = $this->getNextPlatformDeliveryStatus();

        return $this->updateDispatch($order, $params);
    }

    /**
     * 更新订单配送员
     * @param mixed $order
     * @param array $params
     * @param int $serviceLogId
     * @return bool
     */
    protected function updateDispatch($order, array $params, int $serviceLogId = 0)
    {
        $stationType = (int)$order->deliveryOrder->station_type;
        $this->checkDeliveryService((int)$params['service_id'], $stationType, (int)$order['mer_id']);

        //入库操作
        try {
            Db::transaction(function () use ($params, $order, $serviceLogId) {
                // 更新配送员id
                $order->deliveryOrder->service_id = $params['service_id'];
                if (array_key_exists('status', $params)) {
                    $order->deliveryOrder->status = $params['status'];
                }
                if ((int)$order->deliveryOrder->status === 3 && empty($order->deliveryOrder->pickup_time)) {
                    $order->deliveryOrder->pickup_time = date('Y-m-d H:i:s');
                }
                $order->deliveryOrder->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已改派',
                    'change_type' => StoreOrderStatusRepository::DELIVERY_ORDER_DISPATCH,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 订单信息
     * @param int $orderId
     * @param int $merId
     * @param string $status
     * @return mixed
     */
    protected function orderInfo(int $orderId, int $merId, $status = '')
    {
        $where = ['order_id' => $orderId, 'mer_id' => $merId, 'order_type' => 2, 'paid' => 1, 'is_del' => 0];
        if ($status !== '') {
            $where['status'] = $status;
        }
        $order = app()->make(StoreOrderRepository::class)->getWhere($where);
        if (!$order) {
            throw new ValidateException('订单状态异常，请检查');
        }

        return $order;
    }

    /**
     * 获取平台配送订单
     * @param int $orderId
     * @param string|int $status
     * @return mixed
     */
    protected function platformOrderInfo(int $orderId, $status = '')
    {
        $where = [
            'order_id' => $orderId,
            'order_type' => 2,
            'city_delivery_type' => DeliveryStation::TYPE_PLATFORM,
            'paid' => 1,
            'is_del' => 0,
        ];
        if ($status !== '') {
            $where['status'] = $status;
        }
        $order = app()->make(StoreOrderRepository::class)->getWhere($where);
        if (!$order) {
            throw new ValidateException('订单不是待操作的平台配送订单，请检查订单状态');
        }

        return $order;
    }

    /**
     * 校验平台配送开关
     * @return void
     */
    protected function checkPlatformDeliveryStatus(): void
    {
        if ((int)systemConfig('platform_delivery_status') !== 1) {
            throw new ValidateException('平台配送已关闭，暂时无法操作');
        }
    }

    /**
     * 校验配送员是否可接单
     * @param int $serviceId
     * @param int $stationType
     * @param int $merId
     * @return void
     */
    protected function checkDeliveryService(int $serviceId, int $stationType, int $merId): void
    {
        $where = [
            'service_id' => $serviceId,
            'status' => DeliveryService::STATUS_ENABLED,
            'is_del' => 0,
        ];
        if ($stationType === DeliveryStation::TYPE_PLATFORM) {
            $where['type'] = DeliveryService::TYPE_PLATFORM;
            $where['mer_id'] = 0;
        } else {
            $where['type'] = DeliveryService::TYPE_MERCHANT;
            $where['mer_id'] = $merId;
        }

        $service = app()->make(DeliveryServiceRepository::class)->getWhere($where);
        if (!$service) {
            throw new ValidateException('配送员不存在或当前不可接单');
        }
    }

    /**
     * 获取平台配送流程的下一状态
     * @param int|null $currentStatus null 表示从平台派单开始计算
     * @return int
     */
    protected function getNextPlatformDeliveryStatus(?int $currentStatus = null): int
    {
        if ($currentStatus !== null && !in_array($currentStatus, [0, 1, 2], true)) {
            throw new ValidateException('当前配送状态没有可执行的下一步操作');
        }

        $config = systemConfig([
            'platform_delivery_accept_status',
            'platform_delivery_pickup_status',
            'platform_delivery_start_status',
        ]);
        $statusConfig = [
            0 => 'platform_delivery_accept_status',
            1 => 'platform_delivery_pickup_status',
            2 => 'platform_delivery_start_status',
        ];
        $startStatus = $currentStatus === null ? 0 : $currentStatus + 1;
        for ($status = $startStatus; $status <= 2; $status++) {
            if ((int)($config[$statusConfig[$status]] ?? 0) === 1) {
                return $status;
            }
        }

        return 3;
    }

    /**
     * 平台配送员更新配送状态
     * @param int $orderId
     * @param int $serviceId
     * @param int $currentStatus
     * @param array|string $pickupImages 到店取货留底图片
     * @return int
     */
    public function serviceUpdateStatus(int $orderId, int $serviceId, int $currentStatus, $pickupImages = [])
    {
        $this->checkPlatformDeliveryStatus();
        $this->checkDeliveryService($serviceId, DeliveryStation::TYPE_PLATFORM, 0);

        $order = $this->platformOrderInfo($orderId, 1);
        $deliveryOrder = $order->deliveryOrder;
        if (!$deliveryOrder || (int)$deliveryOrder->station_type !== DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送记录不存在，无法确认接单');
        }
        if ((int)$deliveryOrder->service_id !== $serviceId) {
            throw new ValidateException('订单未指派给当前配送员');
        }
        try {
            return Db::transaction(function () use ($order, $deliveryOrder, $serviceId, $currentStatus, $pickupImages) {
                $lockedDeliveryOrder = $this->dao->query([
                    'delivery_order_id' => $deliveryOrder->delivery_order_id,
                ])->lock(true)->find();
                if (!$lockedDeliveryOrder) {
                    throw new ValidateException('平台配送记录不存在');
                }
                if ((int)$lockedDeliveryOrder->service_id !== $serviceId) {
                    throw new ValidateException('订单已改派，请刷新后重试');
                }
                if ((int)$lockedDeliveryOrder->status !== $currentStatus) {
                    throw new ValidateException('订单配送状态已变更，请刷新后重试');
                }

                $nextStatus = $this->getNextPlatformDeliveryStatus($currentStatus);
                $lockedDeliveryOrder->status = $nextStatus;
                if (!empty($pickupImages)) {
                    $lockedDeliveryOrder->pickup_images = $pickupImages;
                }
                // ORDER_DELIVERY_CITY_ING 是日志类型字符串，配送单状态本身使用整数 3。
                if ((int)$nextStatus === 3 && empty($lockedDeliveryOrder->pickup_time)) {
                    $lockedDeliveryOrder->pickup_time = date('Y-m-d H:i:s');
                }

                $lockedDeliveryOrder->save();

                $changeMessages = [
                    0 => '配送员已确认接单',
                    1 => '配送员已到达提货点',
                    2 => '配送员已开始送货',
                ];
                app()->make(StoreOrderStatusRepository::class)->createDeliveryLog($serviceId, [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => $changeMessages[$currentStatus],
                    'change_type' => StoreOrderStatusRepository::DELIVERY_ORDER_DISPATCH,
                ]);
                return $nextStatus;
            });
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ValidateException('配送状态更新失败：' . $e->getMessage());
        }
    }

    /**
     * 配送订单确认
     * @param int $id
     * @param int $merId
     * @param int $serviceLogId
     * @param array|string $confirmImages 确认送达图片
     * @return bool
     */
    public function confirm(int $orderId, int $merId, int $serviceLogId = 0, $confirmImages = [])
    {
        $order = $this->orderInfo($orderId, $merId);
        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送订单由平台操作，商户无法确认送达');
        }
        if($order->status == 2) {
            throw new ValidateException('订单已确认！');
        }

        return $this->confirmOrder($order, $serviceLogId, $confirmImages);
    }

    /**
     * 配送员确认商户配送订单送达
     * @param int $orderId
     * @param int $merId
     * @param int $serviceId
     * @param array|string $confirmImages 确认送达图片
     * @return bool
     */
    public function confirmByDeliveryService(int $orderId, int $merId, int $serviceId, $confirmImages = [])
    {
        $this->checkDeliveryService($serviceId, DeliveryStation::TYPE_MERCHANT, $merId);
        $order = $this->orderInfo($orderId, $merId);
        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送订单由平台操作，商户无法确认送达');
        }
        if ($order->status == 2) {
            throw new ValidateException('订单已确认！');
        }
        if (!$order->deliveryOrder || (int)$order->deliveryOrder->service_id !== $serviceId) {
            throw new ValidateException('订单未指派给当前配送员');
        }

        return $this->confirmOrder($order, 0, $confirmImages, $serviceId);
    }

    /**
     * 平台确认送达
     * @param int $orderId
     * @param array|string $confirmImages 确认送达图片
     * @return bool
     */
    public function sysConfirm(int $orderId, $confirmImages = [])
    {
        $this->checkPlatformDeliveryStatus();
        $order = $this->platformOrderInfo($orderId, 1);
        if (!$order->deliveryOrder || (int)$order->deliveryOrder->station_type !== DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送记录不存在，无法确认送达');
        }
        // 平台确认不再校验配送单中间状态（待接单/待取货/配送中等），确认后直接置为已送达
        return $this->confirmOrder($order, 0, $confirmImages);
    }

    /**
     * 平台配送员确认平台配送订单送达
     * @param int $orderId
     * @param int $serviceId
     * @param array|string $confirmImages 确认送达图片
     * @return bool
     */
    public function sysConfirmByDeliveryService(int $orderId, int $serviceId, $confirmImages = [])
    {
        $this->checkPlatformDeliveryStatus();
        $this->checkDeliveryService($serviceId, DeliveryStation::TYPE_PLATFORM, 0);
        $order = $this->platformOrderInfo($orderId, 1);
        $deliveryOrder = $order->deliveryOrder;
        if (!$deliveryOrder || (int)$deliveryOrder->station_type !== DeliveryStation::TYPE_PLATFORM) {
            throw new ValidateException('平台配送记录不存在，无法确认送达');
        }
        if ((int)$deliveryOrder->service_id !== $serviceId) {
            throw new ValidateException('订单未指派给当前配送员');
        }
        if ((int)$deliveryOrder->status !== 3) {
            throw new ValidateException('配送流程尚未完成，无法确认送达');
        }

        return $this->confirmOrder($order, 0, $confirmImages, $serviceId);
    }

    /**
     * 完成配送订单
     * @param mixed $order
     * @param int $serviceLogId
     * @param array|string $confirmImages 确认送达图片
     * @return bool
     */
    protected function confirmOrder($order, int $serviceLogId = 0, $confirmImages = [], int $deliveryServiceId = 0)
    {
        $now = date('Y-m-d H:i:s');
        try {
            Db::transaction(function () use ($order, $serviceLogId, $confirmImages, $deliveryServiceId, $now) {
                // 配送完成
                if($order->deliveryOrder) {
                    $order->deliveryOrder->status = 4;
                    if (!empty($confirmImages)) {
                        $order->deliveryOrder->confirm_images = $confirmImages;
                    }
                    if (empty($order->deliveryOrder->confirm_time)) {
                        $order->deliveryOrder->confirm_time = $now;
                    }
                    $order->deliveryOrder->save();
                    app()->make(DeliveryServiceBillRepository::class)->createDeliveryIncome((int)$order->deliveryOrder->delivery_order_id);
                }
                // 更新订单状态：待评价
                $order->status = 2;
                $order->verify_time = $now;
                $order->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已确认',
                    'change_type' => StoreOrderStatusRepository::ORDER_STATUS_TAKE,
                ];
                //订单记录
                $user = app()->make(UserRepository::class)->get($order['uid']);
                app()->make(StoreOrderRepository::class)->takeAfter($order, $user);
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($deliveryServiceId) {
                    $storeOrderStatusRepository->createDeliveryLog($deliveryServiceId, $orderStatus);
                } elseif ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            try {
                if (systemConfig('order_shipping_open', 0) && (int)$order->pay_type === 2 && (float)$order->pay_price > 0) {
                    $groupOrder = $order->groupOrder;
                    if ($groupOrder && $groupOrder->group_order_sn) {
                        $merchant = app()->make(MerchantRepository::class)->get((int)$order->mer_id);
                        Queue::push(MiniOrderConfirmJob::class, [
                            'out_trade_no' => (string)$groupOrder->group_order_sn,
                            'received_time' => strtotime((string)$order->verify_time) ?: time(),
                            'sub_mchid' => (string)($merchant->sub_mchid ?? ''),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('小程序订单签收同步任务投递失败：' . json_encode([
                    'message' => $e->getMessage(),
                    'order_id' => (int)$order->order_id,
                    'order_sn' => (string)$order->order_sn,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 获取配送订单ID
     * @param array $where
     * @return array
     */
    public function getOrderIds(array $where)
    {
        $orderIds = $this->dao->search($where)->column('order_id');
        return $orderIds;
    }
    /**
     * 配送提成计算
     *
     * @param object $station
     * @param object $order
     * @return array
     */
    protected function getServiceFee($station, $order, ?int $configMerId = null)
    {
        $deliveryConfigRepository = app()->make(DeliveryConfigRepository::class);
        $configMerId = $configMerId ?? (int)$order['mer_id'];
        $deliverySettings = $deliveryConfigRepository->getDeliveryConfig($configMerId);
        if (!$deliverySettings) {
            $deliveryName = $configMerId === 0 ? '平台' : '商户';
            throw new ValidateException($deliveryName . '未设置配送费用信息，无法计算配送费');
        }
        $settlementFee = (float)($order['delivery_settlement_fee'] ?? 0);
        if ($settlementFee > 0) {
            return [$settlementFee, (float)($order['delivery_service_fee'] ?? 0)];
        }
        // 兼容升级前尚未派单的订单，旧订单没有实际配送成本快照。
        if ($order['pay_postage'] > 0) {
            $settlementFee = (float)$order['pay_postage'];
        } else {
            $totalWeight = 0;
            foreach($order['orderProduct'] as $item) {
                $totalWeight += $item['cart_info']['productAttr']['weight'] * $item['product_num'];
            }
            $settlementFee = $deliveryConfigRepository->deliveryFee(
                $station['station_id'],
                $totalWeight,
                $deliverySettings->toArray(),
                $order['user_address'],
                (int)$order['mer_id']
            );
        }

        $serviceFee = bcmul(bcdiv($deliverySettings['commission_rate'], 100, 2), $settlementFee, 2);
        return [$settlementFee, $serviceFee];
    }

    public function getDeliveryTrack($deliveryOrder)
    {
        try {
            $data['order_code'] = $deliveryOrder['order_code'];
            $data['origin_id'] = $deliveryOrder['order_sn'];

            return DeliverySevices::create($deliveryOrder['station_type'], $deliveryOrder['mer_id'])->getDeliveryTrack($data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
