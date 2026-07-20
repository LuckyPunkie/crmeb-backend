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

use app\common\repositories\store\product\ProductRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\RedisCacheService;
use crmeb\services\TimerService;
use think\facade\Cache;
use think\facade\Log;

class AutoUpDownShelvesListen extends TimerService implements ListenerInterface
{

    /**
     * 自动上下架
     * @param $event
     * @return void
     * @author FerryZhao
     * @day 2024/4/3
     */
    public function handle($event): void
    {
        $this->tick(1000 * 5, function () {
            try {
                $today = strtotime("today");
                $time = time() + 60;
                $redis = app()->make(RedisCacheService::class);
                $status = Cache::get(ProductRepository::AUTO_TIME_STATUS);
                $productRepository = app()->make(ProductRepository::class);
                if ($status) {
                    $times = $redis->zRangeByScore(ProductRepository::AUTO_PREFIX_SET.ProductRepository::AUTO_ON_TIME,$today,$time);
                    if ($times) {
                        $productUpIds = $productRepository->autoSwitchShow($times,ProductRepository::AUTO_ON_TIME);
                        Log::info('自动上架商品：'.$productUpIds);
                    }
                    $times = $redis->zRangeByScore(ProductRepository::AUTO_PREFIX_SET.ProductRepository::AUTO_OFF_TIME,$today,$time);
                    if ($times) {
                        $productDownIds = $productRepository->autoSwitchShow($times,ProductRepository::AUTO_OFF_TIME);
                        Log::info('自动下架商品：'.$productDownIds);
                    }
                } else {
                    $productUpIds = $productRepository->getSearch([])
                        ->whereNotNull('auto_on_time')
                        ->where([
                            ['is_show', '=', 2],
                        ])->column('auto_on_time','product_id');

                    $productRepository->bathAutoOntime($productUpIds,$productRepository::AUTO_ON_TIME);
                    $productDownIds = $productRepository->getSearch([])
                        ->whereNotNull('auto_off_time')
                        ->where([
                            ['is_show', '=', 1],
                            ['auto_off_time', '<>', 0],
                            ['auto_off_time', '<=', time()],
                        ])
                        ->column('product_id','auto_off_time');
                    $productRepository->bathAutoOntime($productDownIds,$productRepository::AUTO_OFF_TIME);
                    Cache::set(ProductRepository::AUTO_TIME_STATUS,1);
                }
            } catch (\Exception $e) {
                Log::error('商品自动上下架失败' . var_export($e, true));
            }
        });
    }
}
