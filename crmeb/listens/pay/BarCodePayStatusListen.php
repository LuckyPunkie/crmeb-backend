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
namespace crmeb\listens\pay;

use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\PayStatusService;
use crmeb\services\TimerService;
use think\facade\Cache;
use think\facade\Log;

class BarCodePayStatusListen extends TimerService implements ListenerInterface
{
    public function handle($data): void
    {
        $this->tick(1000 * 3, function () {
            try {
                // 从redis中获取数据
                $redisData = Cache::store('redis')->handler()->hGetAll('bar_code_pay');
                if($redisData) {
                    Log::info('BarCodePayStatusListen 从redis中获取数据：' . json_encode($redisData));
                    foreach($redisData as $key => $value) {
                        // 从数据库中获取订单信息
                        $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere([
                            'paid' => 0,
                            'is_del' => 0,
                            'group_order_sn' => $key
                        ]);
                        // 如果订单不存在，则删除redis中的数据
                        if(!$groupOrder) {
                            $redis = Cache::store('redis')->handler();
                            $redis->hDel('bar_code_pay', $key);
                            continue;
                        }
                        // 查询支付状态
                        $payStatus = (new PayStatusService($value, $groupOrder->getPayParams()))->query();
                        // 如果支付成功，则更新订单状态
                        if (!empty($payStatus)) {
                            app()->make(StoreOrderRepository::class)->paySuccess($groupOrder, 0, $payStatus, 1);
                            // 删除redis中的数据
                            $redis = Cache::store('redis')->handler();
                            $redis->hDel('bar_code_pay', $key);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('BarCodePayStatusListen 订单结果变更错误：' . $e->getMessage());
            }
        });
    }
}
