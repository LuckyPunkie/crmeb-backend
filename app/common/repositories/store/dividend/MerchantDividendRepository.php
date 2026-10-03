<?php

namespace app\common\repositories\store\dividend;

use app\common\dao\store\dividend\MerchantDividendBatchDao;
use app\common\dao\store\dividend\MerchantDividendDetailDao;
use app\common\dao\system\merchant\FinancialRecordDao;
use app\common\model\store\dividend\MerchantDividendBatch;
use app\common\model\store\dividend\MerchantDividendDetail;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderProduct;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 商家分红
 *
 * 2026-09-24：新建。平台输入总金额+起止时间，按各商户在该时间段内已支付订单数（人次）占比分账，
 * 生成买单形式的订单进商户订单中心，钱直接进商户余额（下单不扣手续费，提现时按商户自己的费率扣，
 * 复用 FinancialRepository::saveApply() 里已经做好的机制，这里不用额外处理）。
 */
class MerchantDividendRepository extends BaseRepository
{
    /** 分红订单挂靠的系统占位账号，所有商户的分红订单统一挂在这个 uid 下 */
    const SYSTEM_ACCOUNT = 'platform_dividend_system';

    /** 2026-09-24：分红订单专用 order_type，跟现有 0普通/1核销/2虚拟/3卡密/4预约服务 都不冲突 */
    const ORDER_TYPE_DIVIDEND = 5;

    protected $dao;

    public function __construct(MerchantDividendBatchDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 计算各商户应得分红（预览用，不落库、不产生任何副作用，可反复调用）
     *
     * 舍入规则：只截取到分，不四舍五入（bcmath 指定 scale 时本身就是截断不是四舍五入，
     * 正好符合"逢5也舍去"的要求）。各商户金额加总后跟总额之间的差额（尾差），
     * 按约定留在平台不分配，不用额外记录去向。
     *
     * @param string $startTime
     * @param string $endTime
     * @param string $totalAmount
     * @return array{total_amount:string,allocated_amount:string,leftover_amount:string,total_paid_order_count:int,items:array}
     */
    public function calculate(string $startTime, string $endTime, string $totalAmount): array
    {
        if (bccomp($totalAmount, '0', 2) <= 0) {
            throw new ValidateException('分红总金额必须大于0');
        }
        if (strtotime($endTime) === false || strtotime($startTime) === false || strtotime($endTime) <= strtotime($startTime)) {
            throw new ValidateException('结束时间必须晚于开始时间');
        }

        $rows = Db::name('store_order')
            ->where('paid', 1)
            ->where('pay_time', 'between', [$startTime, $endTime])
            ->field('mer_id, COUNT(*) as cnt')
            ->group('mer_id')
            ->select()
            ->toArray();

        $totalCount = 0;
        foreach ($rows as $row) {
            $totalCount += (int)$row['cnt'];
        }
        if ($totalCount <= 0) {
            throw new ValidateException('该时间段内全平台没有已支付订单，无法发起分红');
        }

        $merIds = array_column($rows, 'mer_id');
        $merchants = Db::name('merchant')->whereIn('mer_id', $merIds)->column('mer_name', 'mer_id');

        $items = [];
        $allocated = '0.00';
        foreach ($rows as $row) {
            $count = (int)$row['cnt'];
            if ($count <= 0) continue;
            $ratio = bcdiv((string)$count, (string)$totalCount, 6);
            $amount = bcmul($totalAmount, $ratio, 2);
            if (bccomp($amount, '0', 2) <= 0) continue; // 占比太小、分到0元的不参与
            $allocated = bcadd($allocated, $amount, 2);
            $items[] = [
                'mer_id' => (int)$row['mer_id'],
                'mer_name' => $merchants[$row['mer_id']] ?? '',
                'paid_order_count' => $count,
                'ratio' => $ratio,
                'amount' => $amount,
            ];
        }

        return [
            'total_amount' => $totalAmount,
            'allocated_amount' => $allocated,
            'leftover_amount' => bcsub($totalAmount, $allocated, 2),
            'total_paid_order_count' => $totalCount,
            'items' => $items,
        ];
    }

    /**
     * 确认发放：重新按同样的口径算一遍（保证跟预览时校验逻辑一致），然后落库+生成订单+入账
     */
    public function issue(string $startTime, string $endTime, string $totalAmount, int $adminId, string $remark = '')
    {
        $calc = $this->calculate($startTime, $endTime, $totalAmount);
        $systemUid = $this->getSystemUid();
        $orderRepository = app()->make(StoreOrderRepository::class);
        $merchantRepository = app()->make(MerchantRepository::class);
        $financialRecordDao = app()->make(FinancialRecordDao::class);

        return Db::transaction(function () use ($calc, $startTime, $endTime, $totalAmount, $adminId, $remark, $systemUid, $orderRepository, $merchantRepository, $financialRecordDao) {
            $batchSn = 'DIV' . date('YmdHis') . mt_rand(1000, 9999);
            $batch = MerchantDividendBatch::create([
                'batch_sn' => $batchSn,
                'total_amount' => $totalAmount,
                'allocated_amount' => $calc['allocated_amount'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_paid_order_count' => $calc['total_paid_order_count'],
                'status' => 1,
                'admin_id' => $adminId,
                'remark' => $remark,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            foreach ($calc['items'] as $item) {
                $order = $this->createDividendOrder($orderRepository, $systemUid, $item['mer_id'], $item['amount'], $batchSn);

                MerchantDividendDetail::create([
                    'batch_id' => $batch['id'],
                    'mer_id' => $item['mer_id'],
                    'paid_order_count' => $item['paid_order_count'],
                    'ratio' => $item['ratio'],
                    'amount' => $item['amount'],
                    'order_id' => $order['order_id'],
                    'order_sn' => $order['order_sn'],
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

                // 商户余额入账（不再走订单支付扣手续费那一套，直接全额进账，跟 2026-09-24 手续费改造后的普通订单逻辑一致）
                $merchantRepository->addLockMoney($item['mer_id'], 'platform_dividend', (int)$order['order_id'], (float)$item['amount']);

                // 资金流水（复用 order_true 的 pm/type 惯例，保证"财务→平台账单"等报表能统计到）
                $financialRecordDao->dec([
                    'order_id' => $order['order_id'],
                    'order_sn' => $order['order_sn'],
                    'user_info' => '平台分红',
                    'user_id' => $systemUid,
                    'financial_type' => 'platform_dividend',
                    'number' => $item['amount'],
                    'type' => 2,
                    'pay_type' => 7,
                ], $item['mer_id']);
            }

            return $this->dao->get($batch['id']);
        });
    }

    /**
     * 合成一条买单形式的订单，写法照抄 NearbyShopBillOrderRepository::syncStoreOrder()
     */
    protected function createDividendOrder(StoreOrderRepository $orderRepository, int $uid, int $merId, string $amount, string $batchSn)
    {
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        $groupOrderSn = $orderRepository->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '0';
        $orderSn = $orderRepository->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '1';
        $payTimeStr = date('Y-m-d H:i:s');
        $storeName = ($merchant['mer_name'] ?? '商家') . ' · 平台分红';
        $image = $merchant['mer_avatar'] ?? '';

        $group = StoreGroupOrder::create([
            'uid' => $uid,
            'group_order_sn' => $groupOrderSn,
            'total_postage' => 0,
            'total_price' => $amount,
            'total_num' => 1,
            'pay_price' => $amount,
            'coupon_price' => 0,
            'pay_postage' => 0,
            'cost' => 0,
            'real_name' => '平台分红',
            'user_phone' => '',
            'user_address' => '',
            'paid' => 1,
            'pay_time' => $payTimeStr,
            'pay_type' => 7,
            'activity_type' => 0,
            'is_remind' => 0,
            'is_del' => 0,
        ]);

        $order = StoreOrder::create([
            'order_sn' => $orderSn,
            'uid' => $uid,
            'spread_uid' => 0,
            'group_order_id' => $group['group_order_id'],
            'mer_id' => $merId,
            'real_name' => '平台分红',
            'user_phone' => '',
            'user_address' => '',
            'cart_id' => '',
            'total_num' => 1,
            'total_price' => $amount,
            'total_postage' => 0,
            'pay_price' => $amount,
            'pay_postage' => 0,
            'commission_rate' => 0,
            'extension_one' => 0,
            'extension_two' => 0,
            'coupon_id' => '',
            'coupon_price' => 0,
            'platform_coupon_price' => 0,
            'svip_discount' => 0,
            'cost' => 0,
            'integral' => 0,
            'integral_price' => 0,
            'give_integral' => 0,
            'pay_type' => 7,
            'paid' => 1,
            'pay_time' => $payTimeStr,
            'status' => 3,
            'order_type' => self::ORDER_TYPE_DIVIDEND,
            'is_virtual' => 1,
            'activity_type' => 0,
            'is_del' => 0,
            'is_system_del' => 0,
            'mark' => '平台分红:' . $batchSn,
            'remark' => '平台分红',
            'task_id' => '',
            'kuaidi_order_id' => '',
            'merchant_take_info' => '',
        ]);

        StoreOrderProduct::create([
            'order_id' => $order['order_id'],
            'uid' => $uid,
            'cart_id' => 0,
            'product_id' => 0,
            'product_sku' => 'platform_dividend',
            'is_refund' => 0,
            'product_num' => 1,
            'product_type' => 0,
            'activity_id' => 0,
            'refund_num' => 1,
            'is_reply' => 0,
            'total_price' => $amount,
            'product_price' => $amount,
            'extension_one' => 0,
            'extension_two' => 0,
            'coupon_price' => 0,
            'platform_coupon_price' => 0,
            'postage_price' => 0,
            'svip_discount' => 0,
            'cost' => 0,
            'integral' => 0,
            'integral_price' => 0,
            'integral_total' => 0,
            'reservation_time_part' => '',
            'settlement_price' => 0,
            'cart_info' => json_encode([
                'product' => [
                    'store_name' => $storeName,
                    'image' => $image,
                    'product_id' => 0,
                ],
                'productAttr' => [
                    'price' => $amount,
                    'image' => $image,
                    'sku' => '平台分红',
                ],
                'product_type' => 0,
                'cart_num' => 1,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $order;
    }

    /**
     * 获取（不存在则创建）分红订单挂靠的系统占位用户
     */
    protected function getSystemUid(): int
    {
        $user = User::getDB()->where('account', self::SYSTEM_ACCOUNT)->find();
        if ($user) {
            return (int)$user['uid'];
        }
        $user = User::create([
            'account' => self::SYSTEM_ACCOUNT,
            'pwd' => md5(uniqid('platform_dividend', true)),
            'nickname' => '平台分红',
            'avatar' => '',
            'last_ip' => '127.0.0.1',
            'user_type' => 'h5',
        ]);
        return (int)$user['uid'];
    }

    public function lst(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where)->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    public function detail(int $id)
    {
        $batch = $this->dao->get($id);
        if (!$batch) {
            throw new ValidateException('批次不存在');
        }
        $details = app()->make(MerchantDividendDetailDao::class)
            ->search(['batch_id' => $id])
            ->with(['merchant' => function ($query) {
                $query->field('mer_id,mer_name,mer_avatar');
            }])
            ->select();
        $data = $batch->toArray();
        $data['details'] = $details;
        return $data;
    }
}
