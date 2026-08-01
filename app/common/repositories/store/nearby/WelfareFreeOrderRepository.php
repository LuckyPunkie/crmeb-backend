<?php

namespace app\common\repositories\store\nearby;

use app\common\model\store\order\StoreOrder;
use app\common\model\store\product\Product;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 网购享免单：分账、双订单关联、分销余额
 */
class WelfareFreeOrderRepository
{
    const CACHE_PREFIX = 'welfare_free_ctx:';
    const WELFARE_LOCK_DAYS = 30;

    public function cacheKey(int $uid): string
    {
        return self::CACHE_PREFIX . $uid;
    }

    public function saveContext(int $uid, array $ctx): void
    {
        Cache::set($this->cacheKey($uid), $ctx, 3600);
    }

    public function getContext(int $uid): ?array
    {
        $ctx = Cache::get($this->cacheKey($uid));
        return is_array($ctx) ? $ctx : null;
    }

    public function clearContext(int $uid): void
    {
        Cache::delete($this->cacheKey($uid));
    }

    /**
     * 校验并组装上下文（前端点击网购享免单时）
     */
    public function buildContext(int $scanMerId, float $billAmount, int $productId): array
    {
        if ($scanMerId <= 0 || $billAmount <= 0 || $productId <= 0) {
            throw new ValidateException('参数错误');
        }
        $product = Product::getDB()->where('product_id', $productId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->find();
        if (!$product) {
            throw new ValidateException('公益商品不存在或已下架');
        }
        $welfareMer = app()->make(MerchantRepository::class)->get((int)$product['mer_id']);
        if (!$welfareMer || (int)($welfareMer['is_welfare_shop'] ?? 0) !== 1) {
            throw new ValidateException('非公益店铺商品');
        }
        $hit = (float)$product['hit_amount'];
        $price = (float)$product['price'];
        if ($hit <= 0 || $billAmount > $hit) {
            throw new ValidateException('当前消费金额未命中该公益商品');
        }
        if ($hit > $price) {
            throw new ValidateException('公益商品配置异常');
        }

        return [
            'scan_mer_id' => $scanMerId,
            'bill_amount' => round($billAmount, 2),
            'product_id' => $productId,
            'welfare_mer_id' => (int)$product['mer_id'],
            'welfare_commission' => round((float)$product['welfare_commission'], 2),
            'product_price' => $price,
        ];
    }

    /**
     * 订单创建后写入公益关联字段
     */
    public function attachToOrder(int $uid, $storeOrder): void
    {
        $ctx = $this->getContext($uid);
        if (!$ctx) {
            return;
        }
        $orderProductId = 0;
        try {
            $orderProductId = (int)Db::name('store_order_product')
                ->where('order_id', (int)$storeOrder['order_id'])
                ->value('product_id');
        } catch (\Throwable $e) {
        }
        if ($orderProductId && (int)$ctx['product_id'] !== $orderProductId) {
            return;
        }

        StoreOrder::getDB()->where('order_id', (int)$storeOrder['order_id'])->update([
            'is_welfare_order' => 1,
            'welfare_bill_amount' => $ctx['bill_amount'],
            'welfare_scan_mer_id' => $ctx['scan_mer_id'],
            'welfare_commission' => $ctx['welfare_commission'],
            'welfare_bill_sn' => '',
        ]);
    }

    /**
     * 公益商品支付成功：入账扫码商户 + 生成买单订单 + 用户侧买单同步单
     */
    public function settleOnPaySuccess($storeOrder): void
    {
        if (empty($storeOrder['is_welfare_order']) || empty($storeOrder['welfare_scan_mer_id'])) {
            return;
        }
        // 幂等：已关联买单则跳过
        if (!empty($storeOrder['welfare_bill_sn'])) {
            return;
        }

        $scanMerId = (int)$storeOrder['welfare_scan_mer_id'];
        $billAmount = (float)$storeOrder['welfare_bill_amount'];
        $commission = (float)$storeOrder['welfare_commission'];
        $uid = (int)$storeOrder['uid'];
        $productId = 0;
        try {
            $productId = (int)Db::name('store_order_product')
                ->where('order_id', (int)$storeOrder['order_id'])
                ->value('product_id');
        } catch (\Throwable $e) {
        }

        $billRepo = app()->make(NearbyShopBillOrderRepository::class);
        $billOrder = $billRepo->createBillOrder([
            'uid' => $uid,
            'mer_id' => $scanMerId,
            'pay_price' => $billAmount,
            'pay_type' => 'welfare_free',
        ]);

        $billSn = (string)$billOrder['order_sn'];
        $now = time();
        Db::name('nearby_shop_bill_order')->where('id', (int)$billOrder['id'])->update([
            'paid' => 1,
            'status' => 1,
            'pay_time' => $now,
            'bill_scene' => 'welfare',
            'welfare_product_id' => $productId,
            'welfare_order_id' => (int)$storeOrder['order_id'],
            'welfare_commission' => $commission,
            'scan_mer_id' => $scanMerId,
            'pay_type' => 'welfare_free',
        ]);

        StoreOrder::getDB()->where('order_id', (int)$storeOrder['order_id'])->update([
            'welfare_bill_sn' => $billSn,
        ]);

        $billOrder = $billRepo->getWhere(['order_sn' => $billSn]);

        // 1) 买单金额入扫码商户可用/冻结余额
        try {
            app()->make(MerchantRepository::class)->addLockMoney(
                $scanMerId,
                'order',
                (int)$billOrder['id'],
                $billAmount
            );
            app()->make(\app\common\dao\system\merchant\FinancialRecordDao::class)->inc([
                'order_id' => (int)$billOrder['id'],
                'order_sn' => $billSn,
                'user_info' => 'uid:' . $uid,
                'user_id' => $uid,
                'financial_type' => 'nearby_bill_welfare',
                'type' => 1,
                'number' => $billAmount,
                'pay_type' => 0,
            ], $scanMerId);
        } catch (\Throwable $e) {
            Log::error('Welfare settle bill money failed: ' . $e->getMessage());
        }

        // 2) 分销金额入锁定分销余额（30天）
        try {
            $this->addWelfareLockMoney($scanMerId, (int)$storeOrder['order_id'], $commission);
        } catch (\Throwable $e) {
            Log::error('Welfare settle commission failed: ' . $e->getMessage());
        }

        // 3) 用户侧同步买单订单 + 语音
        try {
            $billRepo->afterWelfareBillPaid($billOrder, 'welfare_free');
        } catch (\Throwable $e) {
            Log::error('Welfare after bill paid failed: ' . $e->getMessage());
        }

        $this->clearContext($uid);
        Log::info('Welfare settle ok: order_id=' . $storeOrder['order_id'] . ' bill_sn=' . $billSn);
    }

    public function addWelfareLockMoney(int $merId, int $orderId, float $money): void
    {
        if ($money <= 0 || $merId <= 0) {
            return;
        }
        app()->make(UserBillRepository::class)->incBill($merId, 'mer_welfare_lock_money', 'welfare', [
            'link_id' => (string)$orderId,
            'mer_id' => $merId,
            'status' => 0,
            'title' => '公益分销冻结余额',
            'number' => $money,
            'mark' => '公益分销冻结30天',
            'balance' => 0,
        ]);

        app()->make(\app\common\dao\system\merchant\FinancialRecordDao::class)->inc([
            'order_id' => $orderId,
            'order_sn' => (string)$orderId,
            'user_info' => 'welfare',
            'user_id' => 0,
            'financial_type' => 'welfare_commission',
            'type' => 1,
            'number' => $money,
            'pay_type' => 0,
        ], $merId);
    }

    /**
     * 解冻到期的分销余额 → mer_welfare_money
     */
    public function unlockTimeoutWelfareMoney(): void
    {
        $time = date('Y-m-d H:i:s', strtotime('-' . self::WELFARE_LOCK_DAYS . ' day'));
        $bills = Db::name('user_bill')
            ->where('category', 'mer_welfare_lock_money')
            ->where('status', 0)
            ->where('create_time', '<=', $time)
            ->limit(100)
            ->select()
            ->toArray();
        $merchant = app()->make(MerchantRepository::class);
        foreach ($bills as $bill) {
            Db::transaction(function () use ($bill, $merchant) {
                $merId = (int)$bill['mer_id'];
                $num = (float)$bill['number'];
                Db::name('merchant')->where('mer_id', $merId)->inc('mer_welfare_money', $num)->update([]);
                Db::name('user_bill')->where('bill_id', $bill['bill_id'])->update(['status' => 1]);
            });
        }
    }

    /**
     * 退款：扣锁定分销；退用户 max(0, 实付-买单金额)
     * @return float 应退用户钱包金额（调用方按此金额处理，若已走原退款则需覆盖）
     */
    public function handleRefund($storeOrder, float $refundPrice): array
    {
        if (empty($storeOrder['is_welfare_order'])) {
            return ['custom_refund' => false, 'wallet_amount' => $refundPrice];
        }
        $billAmount = (float)($storeOrder['welfare_bill_amount'] ?? 0);
        $paid = (float)($storeOrder['pay_price'] ?? 0);
        $wallet = max(0, round($paid - $billAmount, 2));
        $commission = (float)($storeOrder['welfare_commission'] ?? 0);
        $scanMerId = (int)($storeOrder['welfare_scan_mer_id'] ?? 0);

        if ($scanMerId > 0 && $commission > 0) {
            $this->subWelfareLockMoney($scanMerId, (int)$storeOrder['order_id'], $commission);
        }

        // 更新买单单状态备注
        if (!empty($storeOrder['welfare_bill_sn'])) {
            Db::name('nearby_shop_bill_order')
                ->where('order_sn', $storeOrder['welfare_bill_sn'])
                ->update(['status' => -1]);
        }

        return ['custom_refund' => true, 'wallet_amount' => $wallet, 'bill_amount' => $billAmount];
    }

    public function subWelfareLockMoney(int $merId, int $orderId, float $money): void
    {
        if ($money <= 0) {
            return;
        }
        $bill = Db::name('user_bill')
            ->where('category', 'mer_welfare_lock_money')
            ->where('mer_id', $merId)
            ->where('link_id', (string)$orderId)
            ->where('status', 0)
            ->find();
        if ($bill) {
            Db::name('user_bill')->where('bill_id', $bill['bill_id'])->update([
                'status' => -1,
                'mark' => ($bill['mark'] ?? '') . '|退款扣回',
            ]);
            return;
        }
        // 已解冻则从可提现分销余额扣
        $mer = Db::name('merchant')->where('mer_id', $merId)->field('mer_welfare_money')->find();
        $avail = (float)($mer['mer_welfare_money'] ?? 0);
        if ($avail >= $money) {
            Db::name('merchant')->where('mer_id', $merId)->dec('mer_welfare_money', $money)->update([]);
        } else {
            if ($avail > 0) {
                Db::name('merchant')->where('mer_id', $merId)->dec('mer_welfare_money', $avail)->update([]);
            }
            Log::error('Welfare refund commission insufficient', [
                'mer_id' => $merId,
                'order_id' => $orderId,
                'need' => $money,
                'avail' => $avail,
            ]);
        }
    }
}
