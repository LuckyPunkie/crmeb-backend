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

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use app\common\model\system\Relevance;

class StoreGroup extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'store_group_id';
    }

    public static function tableName(): string
    {
        return 'store_group';
    }

    public function getMerchantAttr()
    {
        $merIds = Relevance::where('left_id', $this->store_group_id)
            ->where('type', 'store_group')
            ->column('right_id');

        if (!$merIds) {
            return [];
        }

        return Merchant::whereIn('mer_id', $merIds)
            ->column('mer_id,mer_name,status');
    }

    public function parent()
    {
        return $this->hasOne(StoreGroup::class, 'store_group_id', 'pid')
            ->field('name, store_group_id, pid, sort, status, positioning_status, longitude, latitude, address');
    }

    public function getMerchantCountAttr()
    {
        return count($this->getMerchantAttr());
    }
}
