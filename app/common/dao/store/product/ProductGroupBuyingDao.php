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
use app\common\model\store\product\ProductGroupBuying;

class ProductGroupBuyingDao extends  BaseDao
{
    public function getModel(): string
    {
        return ProductGroupBuying::class;
    }


    /**
     * 根据条件搜索团购信息
     *
     * 本函数用于构建并返回一个根据指定条件搜索产品团购信息的查询语句。
     * 它支持多种搜索条件，包括商家ID、日期、状态、用户名、关键词和是否为交易者等。
     * 搜索功能通过动态拼接查询条件来实现，以适应不同的搜索需求。
     *
     * @param array $where 搜索条件数组，包含各种可能的搜索参数。
     * @return \yii\db\ActiveQuery 返回构建的查询语句对象。
     */
    public function search($where)
    {
        // 初始化查询，从ProductGroupBuying表开始，使用别名B
        $query = ProductGroupBuying::getDb()->alias('B')
            // 加入StoreProductGroup表，通过product_group_id关联
            ->join('StoreProductGroup G','B.product_group_id = G.product_group_id');
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('B.mer_id', $region);
        }
        // 动态添加条件：根据商家ID搜索
        $query
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function($query)use($where){
                // 如果指定了商家ID，则添加到查询条件中
                $query->where('B.mer_id',$where['mer_id']);
            })
            // 动态添加条件：根据日期搜索
            ->when(isset($where['date']) && $where['date'] , function($query)use($where){
                // 如果指定了日期，则调用getModelTime函数处理，并添加到查询条件中
                getModelTime($query,$where['date'],'B.create_time');
            })
            // 动态添加条件：根据状态搜索
            ->when(isset($where['status']) && $where['status'] !== '', function($query)use($where){
                // 如果指定了状态，则添加到查询条件中
                $query->where('B.status',$where['status']);
            })
            // 动态添加条件：根据用户名搜索
            ->when(isset($where['user_name']) && $where['user_name'] !== '', function($query)use($where){
                // 加入StoreProductGroupUser表，通过group_buying_id关联
                // 并搜索初始化者（is_initiator=1），用户名（uid|nickname）包含搜索关键字
                $query->join('StoreProductGroupUser U','U.group_buying_id = B.group_buying_id')
                    ->where('is_initiator',1)
                    ->whereLike('uid|nickname',"%{$where['user_name']}%");
            })
            // 动态添加条件：根据关键词搜索
            ->when(isset($where['keyword']) && $where['keyword'] !== '' , function($query)use($where){
                // 加入StoreProduct表，通过product_id关联
                // 搜索团购ID、产品ID或商店名称包含搜索关键字
                $query->join('StoreProduct P','G.product_id = P.product_id')
                    ->whereLike('B.group_buying_id|P.product_id|store_name',"%{$where['keyword']}%");
            })
            // 动态添加条件：根据是否为交易者搜索
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '', function($query)use($where){
                // 加入Merchant表，通过mer_id关联
                // 并根据是否为交易者（is_trader）进行筛选
                $query->join('Merchant M','M.mer_id = B.mer_id')->where('is_trader',$where['is_trader']);
            })
        ;

        // 返回构建好的查询语句
        return $query;
    }
}
