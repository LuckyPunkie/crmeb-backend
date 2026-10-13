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
use app\common\model\store\product\ProductGroupUser;

class ProductGroupUserDao extends  BaseDao
{
    public function getModel(): string
    {
        return ProductGroupUser::class;
    }

    /**
     * 查询成功参与团购的用户信息
     *
     * 本函数用于查询特定团购活动（由$id$指定）中，状态为“成功”的用户的昵称和头像信息。
     * 通过预先构建查询条件，筛选出参与了团购且团购状态为成功的用户，再进一步限定查询的字段为昵称和头像。
     * 这样做的目的是为了减少数据库查询的负载，只获取必要的用户信息，提高查询效率。
     *
     * @param int $id 团购组的ID，用于指定查询哪个团购组的用户信息。
     * @return array 返回一个包含符合条件的用户昵称和头像信息的数组。
     */
    public function successUser($id)
    {
        // 构建查询条件，筛选出团购状态为“成功”的用户
        $query = ProductGroupUser::hasWhere('groupBuying',function($query){
            $query->where('status',10);
        });
        // 限定查询的用户是属于指定团购组ID的
        $query->where('product_group_id',$id);
        // 设置查询字段，只获取昵称和头像信息，减少数据传输量
        return $query->setOption('field',[])->field('nickname,avatar')->select();
    }

    /**
     * 更新团购状态
     *
     * 此方法用于将指定团购组的状态更新为10。这通常表示团购活动的某种特定状态，比如开启、结束等。
     * 选择状态10的具体含义应该在业务逻辑中有所定义。
     *
     * @param int $groupId 团购组的ID。这个参数用于指定要更新状态的团购组。
     * @return int 返回更新操作影响的行数。这可以用于判断更新操作是否成功。
     */
    public function updateStatus(int $groupId)
    {
        // 通过调用getModel方法获取模型实例，并直接调用其getDb方法来获取数据库连接。
        // 然后使用where方法指定更新条件，这里是group_buying_id等于$groupId。
        // 最后，使用update方法更新指定条件下的记录的状态为10。
        return $this->getModel()::getDb()->where('group_buying_id',$groupId)->update(['status' => 10]);
    }

    /**
     * 根据产品组ID获取相关订单ID列表
     *
     * 本函数旨在查询与特定产品组相关联的订单ID。它通过筛选满足特定条件的记录，
     * 即产品组ID匹配且订单ID大于0，来实现这一目的。
     *
     * @param int $productGroupId 产品组ID，用于查询与该产品组相关的订单。
     * @return array 返回一个包含满足条件的订单ID的数组。
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function groupOrderIds($productGroupId)
    {
        // 使用ProductGroupUser模型的数据库连接，并构造查询条件
        // 筛选group_buying_id等于$productGroupId且order_id大于0的记录
        return ProductGroupUser::getDB()->where('group_buying_id', $productGroupId)->where('order_id', '>', 0)->select();
    }
}
