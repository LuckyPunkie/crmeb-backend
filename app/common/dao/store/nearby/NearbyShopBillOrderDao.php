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

namespace app\common\dao\store\nearby;

use app\common\dao\BaseDao;
use app\common\model\store\nearby\NearbyShopBillOrder;

class NearbyShopBillOrderDao extends BaseDao
{
    protected function getModel(): string
    {
        return NearbyShopBillOrder::class;
    }

    /**
     * 生成买单订单号
     */
    public function makeOrderSn()
    {
        return 'NB' . date('YmdHis') . substr(implode('', array_map(function () {
            return mt_rand(0, 9);
        }, range(1, 6))), 0, 6);
    }
}
