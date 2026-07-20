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


use think\facade\Log;
use app\common\repositories\store\order\StoreOrderProfitsharingRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\OrderProfitsharingJob;
use crmeb\services\TimerService;
use think\facade\Queue;

class AutoOrderProfitsharingListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        //1000 * 60 * 20
        $this->tick(1000 * 60 * 20, function () {
            request()->clearCache();
            $day = (int)systemConfig('sys_refund_timer') ?: 15;
            $time = date('Y-m-d H:i:s',strtotime('-' . $day . ' day'));
            //$time = date('Y-m-d H:i:s', time());
            $ids = app()->make(StoreOrderProfitsharingRepository::class)->getAutoProfitsharing($time);
            foreach ($ids as $id) {
                Log::info('自动分账监听开始'.$id);
                Queue::push(OrderProfitsharingJob::class, $id);
            }
        });
    }
}
