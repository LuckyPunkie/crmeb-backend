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
use crmeb\jobs\OrderProfitsharingStatusJob;
use app\common\repositories\store\order\StoreOrderProfitsharingRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\OrderProfitsharingJob;
use crmeb\services\TimerService;
use think\facade\Queue;

class AutoOrderProfitsharingStatusListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 2, function () {
            request()->clearCache();
            $storeOrderProfitsharingRepository = app()->make(StoreOrderProfitsharingRepository::class);
            $ids = $storeOrderProfitsharingRepository->getSearch([])->where('status',2)->column('profitsharing_id');
            foreach ($ids as $id) {
                Queue::push(OrderProfitsharingStatusJob::class, $id);
            }
        });
    }
}
