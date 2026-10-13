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
use think\facade\Log;
use app\common\repositories\store\product\StoreUserCardRepository;

class ValidateCardEndTimeJob implements JobInterface
{

    public function fire($job, $data)
    {
        try {
            app()->make(StoreUserCardRepository::class)->validateEndTime();
            $job->delete();
        } catch (\Throwable $exception) {
            Log::error(sprintf(
                '次卡到期校验失败 [%s] %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            throw $exception;
        }
    }

    public function failed($data, $exception = null)
    {
        $message = $exception instanceof \Throwable ? sprintf(
            ' [%s] %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ) : '';

        Log::error('次卡到期校验任务达到最大重试次数' . $message);
    }
}
