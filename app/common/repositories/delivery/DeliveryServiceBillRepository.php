<?php

namespace app\common\repositories\delivery;

use app\common\dao\delivery\DeliveryServiceBillDao;
use app\common\model\delivery\DeliveryOrder;
use app\common\model\delivery\DeliveryService;
use app\common\model\delivery\DeliveryServiceBill;
use app\common\model\delivery\DeliveryServiceWithdraw;
use app\common\model\delivery\DeliveryStation;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

class DeliveryServiceBillRepository extends BaseRepository
{
    public const BILL_TYPE_DELIVERY_INCOME = 'delivery_income';
    public const BILL_TYPE_DELIVERY_WITHDRAW = 'delivery_withdraw';

    public const BILL_TYPE_TEXT = [
        self::BILL_TYPE_DELIVERY_INCOME => '配送收入',
        self::BILL_TYPE_DELIVERY_WITHDRAW => '提现支出',
    ];

    public const PM_EXPEND = 0;
    public const PM_INCOME = 1;

    public const PM_TEXT = [
        self::PM_EXPEND => '支出',
        self::PM_INCOME => '收入',
    ];

    public const STATUS_INVALID = -1;
    public const STATUS_PENDING = 0;
    public const STATUS_AVAILABLE = 1;

    public const STATUS_TEXT = [
        self::STATUS_INVALID => '失效',
        self::STATUS_PENDING => '待结算',
        self::STATUS_AVAILABLE => '可提现',
    ];

    public function __construct(DeliveryServiceBillDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 配送完成后生成配送员收入流水。
     */
    public function createDeliveryIncome(int $deliveryOrderId): bool
    {
        return Db::transaction(function () use ($deliveryOrderId) {
            $deliveryOrder = DeliveryOrder::getDB()
                ->where('delivery_order_id', $deliveryOrderId)
                ->lock(true)
                ->find();
            if (!$deliveryOrder) {
                throw new ValidateException('配送订单不存在，无法生成配送收入流水');
            }
            if ((int)$deliveryOrder->status !== 4 || (int)$deliveryOrder->service_id <= 0) {
                return false;
            }

            $deliveryType = (int)$deliveryOrder->station_type;
            if (!in_array($deliveryType, [DeliveryStation::TYPE_MERCHANT, DeliveryStation::TYPE_PLATFORM], true)) {
                return false;
            }
            if ((float)$deliveryOrder->service_fee <= 0) {
                return false;
            }

            $exists = $this->dao->getWhere([
                'delivery_order_id' => $deliveryOrderId,
                'bill_type' => self::BILL_TYPE_DELIVERY_INCOME,
            ]);
            if ($exists) {
                return true;
            }

            $deliveryService = $deliveryOrder->deliveryService;
            if (!$deliveryService) {
                throw new ValidateException('配送员不存在，无法生成配送收入流水');
            }
            $storeOrder = $deliveryOrder->storeOrder;
            if (!$storeOrder) {
                throw new ValidateException('商城订单不存在，无法生成配送收入流水');
            }

            $isPlatform = $deliveryType === DeliveryStation::TYPE_PLATFORM;
            $this->dao->create([
                'bill_sn' => $this->getSn(),
                'service_id' => $deliveryOrder->service_id,
                'uid' => $deliveryService->uid,
                'delivery_order_id' => $deliveryOrder->delivery_order_id,
                'order_id' => $deliveryOrder->order_id,
                'order_sn' => $storeOrder->order_sn,
                'mer_id' => $deliveryOrder->mer_id,
                'delivery_type' => $deliveryType,
                'bill_type' => self::BILL_TYPE_DELIVERY_INCOME,
                'pm' => self::PM_INCOME,
                'amount' => $deliveryOrder->service_fee,
                'status' => self::STATUS_AVAILABLE,
                'title' => '配送订单收入',
                'mark' => $isPlatform ? '平台配送收入' : '商户配送收入',
            ]);

            $updated = DeliveryService::getDB()
                ->where('service_id', $deliveryOrder->service_id)
                ->inc('total_amount', $deliveryOrder->service_fee)
                ->inc('available_amount', $deliveryOrder->service_fee)
                ->update();
            if (!$updated) {
                throw new ValidateException('配送员收入累计失败');
            }

            return true;
        });
    }

    /**
     * 创建配送员提现支出流水。
     */
    public function createWithdrawExpend(DeliveryServiceWithdraw $withdraw): DeliveryServiceBill
    {
        return $this->dao->create([
            'bill_sn' => $this->getSn(),
            'service_id' => $withdraw->service_id,
            'uid' => $withdraw->uid,
            'delivery_order_id' => null,
            'order_id' => 0,
            'order_sn' => '',
            'mer_id' => $withdraw->mer_id,
            'delivery_type' => (int)$withdraw->service_type === DeliveryService::TYPE_PLATFORM
                ? DeliveryStation::TYPE_PLATFORM
                : DeliveryStation::TYPE_MERCHANT,
            'bill_type' => self::BILL_TYPE_DELIVERY_WITHDRAW,
            'pm' => self::PM_EXPEND,
            'amount' => $withdraw->amount,
            'status' => self::STATUS_AVAILABLE,
            'title' => '配送费提现',
            'mark' => (int)$withdraw->service_type === DeliveryService::TYPE_PLATFORM
                ? '平台配送费提现'
                : '商户配送费提现',
        ]);
    }

    /**
     * 配送员日账单。
     */
    public function getDayList(array $serviceIds, int $merId, string $date, int $page, int $limit): array
    {
        $startTime = $date . ' 00:00:00';
        $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +1 day'));
        $query = $this->dao->getSearch([])
            ->with(['orderInfo.deliveryOrder'])
            ->whereIn('service_id', $serviceIds)
            ->where('bill_type', self::BILL_TYPE_DELIVERY_INCOME)
            ->where('pm', self::PM_INCOME)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<', $endTime);
        if ($merId === 0) {
            $query->where('delivery_type', DeliveryStation::TYPE_PLATFORM);
        } else {
            $query->where('mer_id', $merId)
                ->where('delivery_type', DeliveryStation::TYPE_MERCHANT);
        }

        $count = (clone $query)->count();
        $incomeQuery = (clone $query)
            ->where('bill_type', self::BILL_TYPE_DELIVERY_INCOME)
            ->where('pm', self::PM_INCOME);
        $orderCount = (clone $incomeQuery)
            ->where('order_id', '>', 0)
            ->distinct(true)
            ->count('order_id');
        $totalAmount = bcadd('0', (string)(clone $incomeQuery)->sum('amount'), 2);
        $list = $query
            ->field('delivery_service_bill_id,bill_sn,order_id,order_sn,mer_id,delivery_type,bill_type,pm,amount,status,title,mark,create_time')
            ->order('create_time DESC,delivery_service_bill_id DESC')
            ->page($page, $limit)
            ->select();

        return [
            'count' => $count,
            'order_count' => $orderCount,
            'total_amount' => $totalAmount,
            'list' => $list,
        ];
    }

    /**
     * 配送员月账单，按日期分组。
     */
    public function getMonthList(array $serviceIds, int $merId, string $month): array
    {
        $startTime = $month . '-01 00:00:00';
        $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +1 month'));
        $query = $this->dao->getSearch([])
            ->with(['orderInfo.deliveryOrder'])
            ->whereIn('service_id', $serviceIds)
            ->where('bill_type', self::BILL_TYPE_DELIVERY_INCOME)
            ->where('pm', self::PM_INCOME)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<', $endTime);
        if ($merId === 0) {
            $query->where('delivery_type', DeliveryStation::TYPE_PLATFORM);
        } else {
            $query->where('mer_id', $merId)
                ->where('delivery_type', DeliveryStation::TYPE_MERCHANT);
        }
        $records = $query
            ->field('delivery_service_bill_id,bill_sn,order_id,order_sn,mer_id,delivery_type,bill_type,pm,amount,status,title,mark,create_time')
            ->order('create_time DESC,delivery_service_bill_id DESC')
            ->select()
            ->toArray();

        $groups = [];
        $monthOrderIds = [];
        $monthTotalAmount = '0.00';
        foreach ($records as $record) {
            $date = substr((string)$record['create_time'], 0, 10);
            if (!isset($groups[$date])) {
                $groups[$date] = [
                    'date' => $date,
                    'order_ids' => [],
                    'total_amount' => '0.00',
                    'list' => [],
                ];
            }
            if (
                $record['bill_type'] === self::BILL_TYPE_DELIVERY_INCOME
                && (int)$record['pm'] === self::PM_INCOME
            ) {
                if ((int)$record['order_id'] > 0) {
                    $groups[$date]['order_ids'][(int)$record['order_id']] = true;
                    $monthOrderIds[(int)$record['order_id']] = true;
                }
                $groups[$date]['total_amount'] = bcadd(
                    $groups[$date]['total_amount'],
                    (string)$record['amount'],
                    2
                );
                $monthTotalAmount = bcadd($monthTotalAmount, (string)$record['amount'], 2);
            }
            $groups[$date]['list'][] = $record;
        }

        foreach ($groups as &$group) {
            $group['order_count'] = count($group['order_ids']);
            unset($group['order_ids']);
        }
        unset($group);

        return [
            'order_count' => count($monthOrderIds),
            'total_amount' => $monthTotalAmount,
            'list' => array_values($groups),
        ];
    }

    /**
     * 配送员近7天收入图表。
     */
    public function getDayChart(array $serviceIds, int $merId, string $date): array
    {
        $selectedTime = strtotime($date . ' 00:00:00');
        $today = date('Y-m-d');
        sort($serviceIds);
        $periods = [];
        for ($i = 0; $i < 7; $i++) {
            $periods[] = date('Y-m-d', strtotime("-6 days +{$i} days", $selectedTime));
        }

        $amounts = [];
        $queryPeriods = [];
        foreach ($periods as $period) {
            $cacheKey = 'delivery_service_bill:day_chart:' . md5(json_encode([$serviceIds, $merId, $period]));
            if ($period !== $today && Cache::has($cacheKey)) {
                $amounts[$period] = (string)Cache::get($cacheKey);
            } else {
                $queryPeriods[] = $period;
            }
        }

        if ($queryPeriods) {
            $startTime = min($queryPeriods) . ' 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime(max($queryPeriods) . ' +1 day'));
            $query = $this->dao->getSearch([])
                ->whereIn('service_id', $serviceIds)
                ->where('bill_type', self::BILL_TYPE_DELIVERY_INCOME)
                ->where('pm', self::PM_INCOME)
                ->where('create_time', '>=', $startTime)
                ->where('create_time', '<', $endTime);
            if ($merId === 0) {
                $query->where('delivery_type', DeliveryStation::TYPE_PLATFORM);
            } else {
                $query->where('mer_id', $merId)
                    ->where('delivery_type', DeliveryStation::TYPE_MERCHANT);
            }
            $rows = $query
                ->field("DATE_FORMAT(create_time, '%Y-%m-%d') AS period,SUM(amount) AS total_amount")
                ->group('period')
                ->select()
                ->toArray();

            $queryAmounts = array_column($rows, 'total_amount', 'period');
            foreach ($queryPeriods as $period) {
                $amount = bcadd('0', (string)($queryAmounts[$period] ?? 0), 2);
                $amounts[$period] = $amount;
                if ($period !== $today) {
                    $cacheKey = 'delivery_service_bill:day_chart:' . md5(json_encode([$serviceIds, $merId, $period]));
                    Cache::set($cacheKey, $amount, 86400 * 30);
                }
            }
        }

        $list = [];
        foreach ($periods as $period) {
            $list[] = [
                'date' => date('m-d', strtotime($period)),
                'total_amount' => bcadd('0', (string)($amounts[$period] ?? 0), 2),
            ];
        }
        return ['list' => $list];
    }

    /**
     * 配送员近6个月收入图表。
     */
    public function getMonthChart(array $serviceIds, int $merId, string $month): array
    {
        $selectedTime = strtotime($month . '-01 00:00:00');
        $currentMonth = date('Y-m');
        sort($serviceIds);
        $periods = [];
        for ($i = 0; $i < 6; $i++) {
            $periods[] = date('Y-m', strtotime("-5 months +{$i} months", $selectedTime));
        }

        $amounts = [];
        $queryPeriods = [];
        foreach ($periods as $period) {
            $cacheKey = 'delivery_service_bill:month_chart:' . md5(json_encode([$serviceIds, $merId, $period]));
            if ($period !== $currentMonth && Cache::has($cacheKey)) {
                $amounts[$period] = (string)Cache::get($cacheKey);
            } else {
                $queryPeriods[] = $period;
            }
        }

        if ($queryPeriods) {
            $startTime = min($queryPeriods) . '-01 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime(max($queryPeriods) . '-01 +1 month'));
            $query = $this->dao->getSearch([])
                ->whereIn('service_id', $serviceIds)
                ->where('bill_type', self::BILL_TYPE_DELIVERY_INCOME)
                ->where('pm', self::PM_INCOME)
                ->where('create_time', '>=', $startTime)
                ->where('create_time', '<', $endTime);
            if ($merId === 0) {
                $query->where('delivery_type', DeliveryStation::TYPE_PLATFORM);
            } else {
                $query->where('mer_id', $merId)
                    ->where('delivery_type', DeliveryStation::TYPE_MERCHANT);
            }
            $rows = $query
                ->field("DATE_FORMAT(create_time, '%Y-%m') AS period,SUM(amount) AS total_amount")
                ->group('period')
                ->select()
                ->toArray();

            $queryAmounts = array_column($rows, 'total_amount', 'period');
            foreach ($queryPeriods as $period) {
                $amount = bcadd('0', (string)($queryAmounts[$period] ?? 0), 2);
                $amounts[$period] = $amount;
                if ($period !== $currentMonth) {
                    $cacheKey = 'delivery_service_bill:month_chart:' . md5(json_encode([$serviceIds, $merId, $period]));
                    Cache::set($cacheKey, $amount, 86400 * 30);
                }
            }
        }

        $list = [];
        foreach ($periods as $period) {
            $list[] = [
                'month' => date('m', strtotime($period . '-01')),
                'total_amount' => bcadd('0', (string)($amounts[$period] ?? 0), 2),
            ];
        }
        return ['list' => $list];
    }

    /**
     * 平台、商户后台配送员流水列表。
     */
    public function getManageList(
        array $where,
        int $page,
        int $limit,
        int $deliveryType,
        int $merId = 0
    ): array
    {
        $query = $this->getManageSearch($where, $deliveryType, $merId)
            ->where('b.bill_type', '<>', self::BILL_TYPE_DELIVERY_WITHDRAW);
        $count = (clone $query)->count();
        $list = $query->with([
            'deliveryService' => function ($query) {
                $query->field('service_id,uid,type,mer_id,avatar,name,phone');
            },
            'orderInfo' => function ($query) {
                $query->with(['orderProduct','take']);
            },
            'withdrawInfo' => function ($query) {
                $query->field('delivery_service_withdraw_id,delivery_service_bill_id,withdraw_sn,amount,status,payment_method,create_time,pay_time');
            },
        ])->order('b.create_time DESC,b.delivery_service_bill_id DESC')
            ->page($page, $limit)
            ->select();

        return compact('count', 'list');
    }

    /**
     * 获取符合后台流水列表筛选条件的全部记录，用于导出。
     */
    public function getManageExportList(array $where, int $deliveryType, int $merId = 0): array
    {
        $query = $this->getManageSearch($where, $deliveryType, $merId)
            ->order('b.create_time DESC,b.delivery_service_bill_id DESC');

        return [
            'count' => (clone $query)->count(),
            'list' => $query->select(),
        ];
    }

    /**
     * 平台、商户后台配送员流水详情。
     */
    public function getManageDetail(int $id, int $deliveryType, int $merId = 0)
    {
        return $this->getManageSearch([], $deliveryType, $merId)
            ->where('b.delivery_service_bill_id', $id)
            ->with([
                'deliveryService' => function ($query) {
                    $query->field('service_id,uid,type,mer_id,avatar,name,phone');
                },
                'deliveryOrder' => function ($query) {
                    $query->field('delivery_order_id,order_id,station_id,service_id,status,from_address,to_address,fee,service_fee,create_time');
                },
                'orderInfo' => function ($query) {
                    $query->field('order_id,order_sn,uid,mer_id,real_name,user_phone,user_address,total_num,total_price,pay_price,pay_postage,status,create_time,pay_time,verify_time')
                        ->with([
                            'merchant' => function ($query) {
                                $query->field('mer_id,mer_name,mer_avatar');
                            },
                            'orderProduct' => function ($query) {
                                $query->field('order_product_id,order_id,product_id,product_num,product_price,cart_info');
                            },
                        ]);
                },
                'withdrawInfo' => function ($query) {
                    $query->with(['auditAdmin']);
                },
            ])->find();
    }

    protected function getManageSearch(array $where, int $deliveryType, int $merId)
    {
        if (!in_array($deliveryType, [DeliveryStation::TYPE_MERCHANT, DeliveryStation::TYPE_PLATFORM], true)) {
            throw new ValidateException('配送流水类型错误');
        }
        $query = DeliveryServiceBill::getDB()
            ->alias('b')
            ->field('b.*,ds.name AS service_name,ds.phone AS service_phone,ds.avatar AS service_avatar,u.nickname')
            ->join('delivery_service ds', 'ds.service_id = b.service_id', 'left')
            ->join('user u', 'u.uid = b.uid', 'left')
            ->where('b.delivery_type', $deliveryType);
        if ($deliveryType === DeliveryStation::TYPE_MERCHANT) {
            $query->where('b.mer_id', $merId);
        }

        return $query
            ->when(isset($where['bill_sn']) && $where['bill_sn'] !== '', function ($query) use ($where) {
                $query->whereLike('b.bill_sn', "%{$where['bill_sn']}%");
            })
            ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
                $query->whereLike('b.order_sn', "%{$where['order_sn']}%");
            })
            ->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
                $query->where('b.service_id', $where['service_id']);
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('b.uid', $where['uid']);
            })
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('u.nickname', "%{$where['nickname']}%");
            })
            ->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('u.phone|ds.phone', "%{$where['phone']}%");
            })
            ->when(isset($where['bill_type']) && $where['bill_type'] !== '', function ($query) use ($where) {
                $query->where('b.bill_type', $where['bill_type']);
            })
            ->when(isset($where['pm']) && $where['pm'] !== '', function ($query) use ($where) {
                $query->where('b.pm', $where['pm']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('b.status', $where['status']);
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'b.create_time');
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike(
                    'b.bill_sn|b.order_sn|ds.name|ds.phone|u.nickname',
                    "%{$where['keyword']}%"
                );
            });
    }

    protected function getSn(): string
    {
        return 'dsb' . date('YmdHis') . random_int(100000, 999999);
    }
}
