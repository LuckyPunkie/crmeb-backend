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

namespace app\common\dao\system\serve;

use app\common\dao\BaseDao;
use app\common\model\system\serve\ServeOrder;

class ServeOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return ServeOrder::class;
    }

    /**
     * 根据条件搜索服务订单。
     *
     * 该方法通过动态构建查询条件来搜索服务订单。支持的搜索条件包括关键字、交易者类型、类别ID、类型ID、删除状态、订单类型、日期、商家ID和订单状态。
     * 这种灵活的查询机制使得可以根据不同的需求组合各种查询条件，以获取所需的服务订单数据。
     *
     * @param array $where 搜索条件数组，包含各种可能的查询条件。
     * @return \Illuminate\Database\Eloquent\Builder 查询构建器对象，可用于进一步的查询操作或数据获取。
     */
    public function search($where)
    {
        // 初始化服务订单查询构建器，并设置初始条件为存在商家关联数据。
        $query = ServeOrder::hasWhere('merchant', function($query) use($where) {
            // 当关键字存在时，添加模糊搜索条件。
            $query->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use($where) {
                $query->whereLike('mer_keyword|real_name|mer_name', "%{$where['keyword']}%");
            });
            // 当交易者类型条件存在时，添加对应查询条件。
            $query->when(isset($where['is_trader']) && $where['is_trader'] !== '', function ($query) use($where) {
                $query->where('is_trader', $where['is_trader']);
            });
            // 当类别ID条件存在时，添加对应查询条件。
            $query->when(isset($where['category_id']) && $where['category_id'] !== '', function ($query) use($where) {
                $query->where('category_id', $where['category_id']);
            });
            // 当类型ID条件存在时，添加对应查询条件。
            $query->when(isset($where['type_id']) && $where['type_id'] !== '', function ($query) use($where) {
                $query->where('type_id', $where['type_id']);
            });
            // 永久删除的订单不参与查询。
            $query->where('is_del', 0);
        });

        // 当订单类型条件存在时，添加对应查询条件。
        $query->when(isset($where['type']) && $where['type'] !== '', function ($query) use($where) {
            $query->where('ServeOrder.type', $where['type']);
        });

        // 当日期条件存在时，添加对应查询条件。
        $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use($where) {
            getModelTime($query, $where['date'], 'ServeOrder.create_time');
        });

        // 当商家ID条件存在时，添加对应查询条件。
        $query->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use($where) {
            $query->where('ServeOrder.mer_id', $where['mer_id']);
        });

        // 当订单状态条件存在时，添加对应查询条件。
        $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use($where) {
            $query->where('ServeOrder.status', $where['status']);
        });

        // 当删除状态条件存在时，添加对应查询条件。
        $query->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use($where) {
            $query->where('ServeOrder.is_del', $where['is_del']);
        });

        // 返回构建好的查询条件，可用于进一步操作或数据获取。
        return $query;
    }

}
