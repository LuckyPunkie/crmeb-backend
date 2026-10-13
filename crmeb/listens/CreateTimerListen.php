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

class CreateTimerListen implements ListenerInterface
{

    public function handle($event): void
    {

        // 协程模式下使用Timer定时器，每秒触发一次
        if ($event !== 'http server #0') {
            return;
        }

        app()->event->trigger('create_timer');
    }
}
