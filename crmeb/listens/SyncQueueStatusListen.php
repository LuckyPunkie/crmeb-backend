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


use app\common\repositories\user\UserRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\SyncQueueStatusJob;
use crmeb\services\TimerService;
use crmeb\services\YunxinSmsService;
use Swoole\Timer;
use think\facade\Queue;

class SyncQueueStatusListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 5 , function () {
            request()->clearCache();
            Queue::push(SyncQueueStatusJob::class);
        });
    }
}
