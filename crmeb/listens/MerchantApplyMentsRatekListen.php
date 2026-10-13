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

use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\MerchantApplymentsRepository;
use crmeb\services\TimerService;
use Swoole\Timer;
use think\facade\Log;
use crmeb\interfaces\ListenerInterface;

class MerchantApplyMentsRatekListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60 * 24, function () {
            try {
                app()->make(MerchantRepository::class)->getSubjectRate();
            } catch (\Exception $e) {
                Log::info('自动查询分账商户分账比例失败' . date('Y-m-d H:i:s', time()));
            }
        });
    }
}
