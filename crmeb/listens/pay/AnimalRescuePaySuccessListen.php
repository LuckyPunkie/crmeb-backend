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

use app\common\repositories\animal_rescue\AdoptionRepository;
use app\common\repositories\animal_rescue\AnimalRescueRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 流浪动物救助 - 支付成功事件监听
 * 监听 pay_success_order 事件，处理救助捐款和云养支付回调
 * Class AnimalRescuePaySuccessListen
 * @package crmeb\listens\pay
 */
class AnimalRescuePaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        $orderSn = $data['order_sn'] ?? '';
        if (empty($orderSn)) return;

        // 记录支付回调日志
        response_log_write(
            ['message' => 'AnimalRescuePaySuccessListen:', 'request' => [], 'response' => $data],
            'info'
        );

        try {
            // 根据订单编号前缀判断订单类型
            if (strpos($orderSn, 'DN') === 0) {
                // DN前缀：救助捐款订单
                app()->make(AnimalRescueRepository::class)->donatePaySuccess($orderSn);
            } elseif (strpos($orderSn, 'CL') === 0) {
                // CL前缀：云养月捐订单
                app()->make(AnimalRescueRepository::class)->cloudPaySuccess($orderSn);
            }
            if (strpos($orderSn, 'AD') === 0) {
                // AD前缀：领养保证金订单
                app()->make(AdoptionRepository::class)->depositPaySuccess($orderSn);
            }
        } catch (\Exception $e) {
            Log::error('AnimalRescuePaySuccessListen error: ' . $e->getMessage() . ' order_sn=' . $orderSn . ' trace=' . substr($e->getTraceAsString(), 0, 500));
            // 写入失败日志便于后续人工补偿
            Log::channel('file')->error('animal_rescue_pay_fail|' . $orderSn . '|' . $e->getMessage());
        }
    }
}
