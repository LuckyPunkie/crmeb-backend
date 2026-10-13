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

use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\CloseSvipCouponJob;
use crmeb\jobs\SendSvipCouponJob;
use crmeb\services\TimerService;
use think\facade\Cache;
use think\facade\Queue;

class SendSvipCouponListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60 * 23, function () {
            $day = date('d', time());
            if ((int)$day === 1) {
                Queue::push(SendSvipCouponJob::class, []);
            }
            $nuxt = strtotime(date('Y-m-01', time()) . ' +1 month');
            $last = strtotime(date('Y-m-d', $nuxt) . " -1 day");

            if ($last - time() < 300) {
                Queue::push(CloseSvipCouponJob ::class, []);
            }
        });
    }
}
