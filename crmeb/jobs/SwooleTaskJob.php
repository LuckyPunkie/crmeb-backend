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
use crmeb\listens\SwooleTaskListen;
use think\facade\Log;

class SwooleTaskJob implements JobInterface
{
    public function fire($job, $data)
    {
        try {
            app()->make(SwooleTaskListen::class)->handle($data);
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('SwooleTaskJob 执行失败：' . $e->getMessage() . '；data=' . var_export($data, true));
            $job->failed($e);
            $job->delete();
        }
    }

    public function failed($data, $e = null)
    {
        $message = $e instanceof \Throwable ? '；error=' . $e->getMessage() : '';
        Log::error('SwooleTaskJob 失败：' . var_export($data, true) . $message);
    }
}
