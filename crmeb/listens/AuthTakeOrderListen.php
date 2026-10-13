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


namespace crmeb\listens;


use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\OrderReplyJob;
use crmeb\services\TimerService;
use Swoole\Timer;
use think\facade\Log;
use think\facade\Queue;

class AuthTakeOrderListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60, function () {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            request()->clearCache();
            /**
             *  设置自动确认收获的时间默认为 15
             *  查询确认收货 15天前发货，再向前查询15天；
             *  超过这个时间未处理的订单将不做处理，
             *  比如：发货时间 10-1 ； 自动确认时间：15天，当10-15日查询的范围：9-15 ～ 10-15 期间未处理订单，9-15日以前订单不做处理
             */
            $timer = ((int)systemConfig('auto_take_order_timer')) ?: 15;
            $end = date('Y-m-d H:i:s', strtotime("- $timer day"));
            $start = date('Y-m-d H:i:s', strtotime("- 15 day",strtotime($end)));
            $ids = app()->make(StoreOrderStatusRepository::class)->getTimeoutDeliveryOrder($start,$end);
            foreach ($ids as $id) {
                try {
                    $storeOrderRepository->takeOrder($id);
                    Queue::push(OrderReplyJob::class, $id);
                } catch (\Exception $e) {
                    Log::error('自动收货失败:['. $id .']'. $e->getMessage());
                }
            }
        });
    }
}
