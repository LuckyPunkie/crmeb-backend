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


namespace crmeb\jobs;


use crmeb\interfaces\JobInterface;
use think\facade\Cache;
use think\facade\Log;
use think\queue\Job;

class SyncQueueStatusJob implements JobInterface
{

    public function fire($job, $data)
    {
        $key =env('APP_KEY','merchant').'_queue_status';
        Cache::set($key,time(),60 * 6);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
