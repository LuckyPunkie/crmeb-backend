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

use app\common\dao\store\nearby\NearbyShopBillOrderDao;
use app\common\model\store\nearby\NearbyShopBillOrder;
use app\common\repositories\BaseRepository;
use think\facade\Db;

class NearbyShopBillOrderRepository extends BaseRepository
{
    public function __construct(NearbyShopBillOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建买单订单
     */
    public function createBillOrder(array $data)
    {
        $orderData = [
            'order_sn' => $this->dao->makeOrderSn(),
            'uid' => $data['uid'],
            'mer_id' => $data['mer_id'],
            'pay_price' => $data['pay_price'],
            'order_price' => $data['pay_price'],
            'pay_type' => $data['pay_type'],
            'status' => 0,
            'paid' => 0,
        ];

        if (!empty($data['coupon_id'])) {
            $orderData['coupon_id'] = $data['coupon_id'];
        }

        return $this->dao->create($orderData);
    }

    /**
     * 支付成功回调处理
     */
    public function paySuccess($orderSn, $payType = 'weixin', $transactionId = '')
    {
        $order = $this->dao->getWhere(['order_sn' => $orderSn]);
        if (!$order || $order['paid'] == 1) {
            return false;
        }

        $updateData = [
            'paid' => 1,
            'status' => 1,
            'pay_time' => time(),
            'pay_type' => $payType,
        ];

        // 幂等保护：WHERE paid=0 防止并发回调重复处理
        $affected = NearbyShopBillOrder::getDB()
            ->where('id', $order['id'])
            ->where('paid', 0)
            ->update($updateData);

        if (!$affected) {
            // 已被其他并发回调处理完毕，幂等返回
            \think\facade\Log::info('NearbyBill paySuccess: already paid ' . $orderSn);
            return $order;
        }

        // 写入财务记录 - 平台分账逻辑
        // 实际项目请根据系统分账配置补充
        try {
            $this->recordFinancial($order, $updateData);
        } catch (\Exception $e) {
            \think\facade\Log::error('NearbyBill paySuccess financial record failed: ' . $e->getMessage());
        }

        return $order;
    }

    /**
     * 记录财务流水（平台分账逻辑占位符）
     * 实际接入时请根据系统分账配置（merchant_divide）补充完整财务记录
     */
    protected function recordFinancial($order, $updateData)
    {
        // TODO: 根据系统分账比例写入平台抽成流水
        // 参考 CRMEB 现有商品订单分账模式:
        //   FinancialRepository::createOrderFinancial($order['id'], $order['order_sn'],
        //       $order['pay_price'], $order['pay_type'], 'nearby_bill');
        \think\facade\Log::info('NearbyBill financial record placeholder: ' . ($order['order_sn'] ?? 'unknown'));
    }
}
