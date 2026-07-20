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


namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\ProductTake;
use think\facade\Db;

class ProductTakeDao extends BaseDao
{

    protected function getModel(): string
    {
        return ProductTake::class;
    }

    /**
     * 更改产品状态
     *
     * 本函数用于将指定产品的状态从0更改为1。这通常表示产品的状态从未处理变为已处理或可用。
     * 参数$id是需要更改状态的产品的唯一标识符。
     *
     * @param int $id 产品的唯一标识符
     */
    public function changeStatus($id)
    {
        // 使用搜索条件找到特定产品，并将其状态更新为1
        $this->getSearch(['product_id' => $id,'status' => 0])->update(['status' => 1]);
    }
}
