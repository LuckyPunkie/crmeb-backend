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

use think\facade\Log;
use crmeb\interfaces\JobInterface;
use app\common\repositories\store\product\ProductRepository;

class SetSeckillStockCacheJob implements JobInterface
{
    public function fire($job, $data)
    {
        try{
            app()->make(ProductRepository::class)->seckillStockCache($data['res'], true);
        }catch (\Exception $e){
            Log::info('[SetSeckillStockCacheJob] 秒杀缓存重置失败：'.$e->getMessage());
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
