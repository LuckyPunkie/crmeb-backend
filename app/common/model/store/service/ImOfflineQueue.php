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

namespace app\common\model\store\service;

use app\common\model\BaseModel;

class ImOfflineQueue extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'im_offline_queue';
    }

    public function msgLog()
    {
        return $this->hasOne(StoreServiceLog::class, 'service_log_id', 'msg_log_id');
    }

    const STATUS_PENDING   = 0;
    const STATUS_DELIVERED = 1;
    const STATUS_EXPIRED   = 2;

    const MAX_RETRY = 10;
}
