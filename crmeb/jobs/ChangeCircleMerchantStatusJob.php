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

use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\merchant\MerchantCoreService;
use think\facade\Log;
use think\facade\Queue;

class ChangeCircleMerchantStatusJob implements JobInterface
{
    /**
     * 同步商圈关联商户、优惠券和客服状态。
     *
     * @param mixed $job 队列任务实例，需支持 delete 方法。
     * @param array $data 任务数据，包含 merchant_ids 和 status。
     * @return void
     */
    public function fire($job, $data)
    {
        try {
            $merchantIds = $data['merchant_ids'] ?? [];
            $status = (int)($data['status'] ?? 0);
            if ($merchantIds) {
                app()->make(MerchantCoreService::class)->writeMerchant(null, ['status'], function () use ($merchantIds, $status) {
                    app()->make(MerchantRepository::class)->search(['mer_id' => $merchantIds])->update(['status' => $status]);
                }, 'circle_merchant_status_sync');
                app()->make(StoreCouponRepository::class)->getSearch([])->whereIn('mer_id', $merchantIds)->update(['status' => $status]);
                app()->make(StoreServiceRepository::class)->getSearch([])->whereIn('mer_id', $merchantIds)->update(['status' => $status]);
                foreach ($merchantIds as $merchantId) {
                    Queue::push(ChangeMerchantStatusJob::class, $merchantId);
                }
            }
        } catch (\Exception $e) {
            Log::error('商圈店铺状态同步失败：' . $e->getMessage());
        }
        $job->delete();
    }

    /**
     * 队列任务失败回调。
     *
     * @param mixed $data 失败任务数据。
     * @return void
     */
    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
