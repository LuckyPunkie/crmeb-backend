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

namespace app\common\dao\store\order;

use app\common\dao\BaseDao;
use app\common\model\store\order\StoreOrderReceipt;
use app\common\model\user\User;

class StoreOrderReceiptDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreOrderReceipt::class;
    }

    /**
     * 根据条件搜索订单收据信息
     *
     * @param array $where 搜索条件
     * @return \think\db\Query 查询结果对象
     */
    public function search(array $where)
    {
        // 当订单类型或关键字存在时，细化查询条件
        if((isset($where['order_type']) && $where['order_type'] !== '') || (isset($where['keyword']) && $where['keyword'] !== '')){
            $query = StoreOrderReceipt::hasWhere('storeOrder',function($query)use($where){
                // 根据订单类型设置查询条件
                switch ($where['order_type'])
                {
                    case 1: // 未支付
                        $query->where('StoreOrder.paid',0)->where('StoreOrder.is_del',0);
                        break;
                    case 2: // 待发货
                        $query->where('StoreOrder.paid',1)->where('StoreOrder.status',0)->where('StoreOrder.is_del',0);
                        break;
                    case 3: // 待收货
                        $query->where('StoreOrder.status',1)->where('StoreOrder.is_del',0);
                        break;
                    case 4: // 待评价
                        $query->where('StoreOrder.status',2)->where('StoreOrder.is_del',0);
                        break;
                    case 5: // 交易完成
                        $query->where('StoreOrder.status',3)->where('StoreOrder.is_del',0);
                        break;
                    case 6: // 已退款
                        $query->where('StoreOrder.status',-1)->where('StoreOrder.is_del',0);
                        break;
                    case 7: // 已删除
                        $query->where('StoreOrder.is_del',1);
                        break;
                    case 8: // 全部
                        $query->where('StoreOrder.is_del', 0);
                        break;
                    default:
                        $query->where(true);
                        break;
                }
                // 当关键字存在时，搜索订单号、真实姓名或用户电话中包含关键字的记录
                $query->when(isset($where['keyword']) && $where['keyword'] !== '' ,function($query)use($where){
                    $query->whereLike("order_sn|real_name|user_phone","%{$where['keyword']}%");
                });
            });
        }else{
            // 如果没有指定订单类型和关键字，直接查询所有订单收据
            $query = StoreOrderReceipt::alias('StoreOrderReceipt');
        }

        // 根据状态筛选
        $query->when(isset($where['status']) && $where['status'] !== '' ,function($query)use($where){
            $query->where('status',$where['status']);
        });
        // 根据日期范围筛选
        $query->when(isset($where['date']) && $where['date'] !== '' ,function($query)use($where){
            getModelTime($query,$where['date'],'create_time');
        });
        // 根据收据号筛选
        $query->when(isset($where['receipt_sn']) && $where['receipt_sn'] !== '' ,function($query)use($where){
            $query->where('receipt_sn',$where['receipt_sn']);
        });
        // 根据用户名筛选
        $query->when(isset($where['username']) && $where['username'] !== '' ,function($query)use($where){
            $uid = User::whereLike('nickname|phone',"%{$where['username']}%")->column('uid');
            $query->where('uid','in',$uid);
        });
        $query->when(isset($where['phone']) && $where['phone'] !== '' ,function($query)use($where){
            $uid = User::whereLike('phone',"%{$where['phone']}%")->column('uid');
            $query->whereIn('uid',$uid);
        });
        $query->when(isset($where['nickname']) && $where['nickname'] !== '' ,function($query)use($where){
            $uid = User::whereLike('nickname',"%{$where['nickname']}%")->column('uid');
            $query->where('uid','in',$uid);
        });
        // 根据商户ID筛选
        $query->when(isset($where['mer_id']) && $where['mer_id'] !== '' ,function($query)use($where){
            $query->where('mer_id',$where['mer_id']);
        });
        // 根据用户ID筛选
        $query->when(isset($where['uid']) && $where['uid'] !== '' ,function($query)use($where){
            $query->where('uid',$where['uid']);
        });

        // 按创建时间降序排序
        return $query->order('create_time DESC');
    }

    /**
     * 根据收据编号更新数据
     *
     * 本函数旨在通过给定的收据序列号（$receipt_sn）更新相关数据。它利用了Model的数据库操作方法，
     * 通过查询到匹配收据序列号的记录，然后对这些记录进行数据更新操作。
     *
     * @param string $receipt_sn 收据序列号，用于定位需要更新的记录
     * @param array $data 包含需要更新的数据的数组
     * @return int 返回影响的行数，表示更新操作的结果
     */
    public function updateBySn(string $receipt_sn, $data)
    {
        // 调用getModel方法获取Model实例，并通过链式调用getDB方法获取数据库操作对象
        // 使用where方法指定更新条件为receipt_sn字段等于传入的收据序列号
        // 最后调用update方法执行数据更新操作，并返回影响的行数
        return $this->getModel()::getDB()->where('receipt_sn', $receipt_sn)->update($data);
    }


    /**
     * 根据订单ID删除相关记录
     *
     * 本函数旨在通过指定的订单ID，从数据库中删除与该订单相关的记录。
     * 这是一个高级操作，需要谨慎使用，以避免误删数据。
     *
     * @param int $id 订单ID，用于定位要删除的记录
     * @return int 返回删除操作影响的行数，用于确认删除操作的效果
     */
    public function deleteByOrderId($id)
    {
        // 调用getModel方法获取模型实例，并直接调用其getDB方法获取数据库连接
        // 然后使用where方法指定删除条件为order_id等于$id，最后执行delete方法进行删除操作
        return $this->getModel()::getDB()->where('order_id',$id)->delete();
    }

}
