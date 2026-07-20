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


namespace crmeb\listens\pay;


use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

class OrderPaySuccessListen implements ListenerInterface
{

    public function handle($data): void
    {
        $orderSn = $data['order_sn'];
        $is_combine = $data['is_combine'] ?? 0;
        $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
        if (!$groupOrder || $groupOrder->paid == 1) return;
        $orders = [];
        if ($is_combine == 1) {
            foreach ($data['data']['sub_orders'] as $order) {
                $orders['sub_orders'][$order['out_trade_no']] = $order;
            }
        } else {
            $orders['transaction_id'] = $data['data']['transaction_id'] ?? $data['data']['trade_no'];
        }
        response_log_write(
            ['message' => 'OrderPaySuccessListen:', 'request' => [], 'response' => $data],
            'info'
        );
        app()->make(StoreOrderRepository::class)->paySuccess($groupOrder, $is_combine, $orders);
    }

    public function ss()
    {
        halt('ss');
    }
}
