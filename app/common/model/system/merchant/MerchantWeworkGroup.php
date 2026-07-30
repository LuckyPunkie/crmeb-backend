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

/**
 * 商户/分店企业微信顾客群配置
 */
class MerchantWeworkGroup extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'merchant_wework_group';
    }

    public function searchMerIdAttr($query, $value)
    {
        $query->where('mer_id', $value);
    }

    public function searchBranchIdAttr($query, $value)
    {
        $query->where('branch_id', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }
}
