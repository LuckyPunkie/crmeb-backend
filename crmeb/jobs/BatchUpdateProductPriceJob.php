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

use app\common\repositories\store\product\ProductBatchProcessRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class BatchUpdateProductPriceJob implements JobInterface
{

    public function fire($job, $data)
    {
        try {
            app()->make(ProductBatchProcessRepository::class)->batchPrice($data);
            $job->delete();
        } catch (\Exception $exception) {
            Log::info(var_export($exception, 1));
        }
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
