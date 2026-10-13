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
use app\common\model\store\product\ProductGroup;
use app\common\repositories\store\product\SpuRepository;

class ProductGroupDao extends  BaseDao
{
    public function getModel(): string
    {
        return ProductGroup::class;
    }

    /**
     * 商品分组查询
     * @param $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/17
     */
    public function search($where)
    {
        $query = ProductGroup::hasWhere('product',function($query)use($where){
            $query->where('status',1);
            $query->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                $query->whereLike('store_name',"%{$where['keyword']}%");
            });
        });
        $query->join('StoreSpu U','ProductGroup.product_group_id = U.activity_id')->where('U.product_type',4);
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('ProductGroup.mer_id', $region);
        }
        $query->when(isset($where['is_show']) && $where['is_show'] !== '',function($query)use($where){
                $query->where('ProductGroup.is_show',$where['is_show']);
            })
            ->when(isset($where['product_status']) && $where['product_status'] !== '',function($query)use($where){
                if($where['product_status'] == -1){
                    $query->where('ProductGroup.product_status','in',[-1,-2]);
                }else{
                    $query->where('ProductGroup.product_status',$where['product_status']);
                }
            })
            ->when(isset($where['status']) && $where['status'] !== '',function($query)use($where){
                $query->where('ProductGroup.status',$where['status']);
            })
            ->when(isset($where['end_time']) && $where['end_time'] !== '',function($query)use($where){
                $query->whereTime('ProductGroup.end_time','>',$where['end_time']);
            })
            ->when(isset($where['active_type']) && $where['active_type'] !== '',function($query)use($where){
                $query->where('ProductGroup.action_status',$where['active_type']);
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '',function($query)use($where){
                $query->join('Merchant M','M.mer_id = ProductGroup.mer_id')->where('M.is_trader',$where['is_trader']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                if(is_array($where['mer_id'])) {
                    $query->whereIn('ProductGroup.mer_id', $where['mer_id']);
                } else {
                    $query->where('ProductGroup.mer_id',$where['mer_id']);
                }
            })
            ->when(isset($where['product_group_id']) && $where['product_group_id'] !== '',function($query)use($where){
                $query->where('ProductGroup.product_group_id',$where['product_group_id']);
            })
             ->when(isset($where['store_category_id']) && $where['store_category_id'] !== '',function($query)use($where){
                 $query->join('StoreCategory C','Product.cate_id = C.store_category_id')
                     ->whereLike('path',"/{$where['store_category_id']}/%");
             })
            ->when(isset($where['us_status']) && $where['us_status'] !== '',function($query)use($where){
                if($where['us_status'] == 0) {
                    $query->where('ProductGroup.is_show',0)->where('ProductGroup.status',1)->where('ProductGroup.product_status',1);
                }
                if($where['us_status'] == 1) {
                    $query->where('ProductGroup.is_show',1)->where('ProductGroup.status',1)->where('ProductGroup.product_status',1);
                }
                if($where['us_status'] == -1) {
                    $query->where(function($query){
                        $query->where('ProductGroup.status',0)->whereOr('ProductGroup.product_status','<>',1);
                    });
                }
            });



        $query->when(isset($where['star']) && $where['star'] !== '',function($query)use($where){
                $query->where('U.star',$where['star']);
            })
            ->when(isset($where['level']) && $where['level'] !== '',function($query)use($where) {
                $query->where('U.star',$where['level']);
            })
            ->when(isset($where['mer_labels']) && $where['mer_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.mer_labels', "%,{$where['mer_labels']},%");
            })
            ->when(isset($where['sys_labels']) && $where['sys_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.sys_labels', "%,{$where['sys_labels']},%");
            });
        if(isset($where['order'])) {
            switch ($where['order']) {
                case 'sort':
                    $order = 'U.sort DESC';
                    break;
                case 'rank':
                    $order = 'U.rank DESC';
                    break;
                case 'star':
                    $order = 'U.star DESC,U.rank DESC';
                    break;
                default:
                    $order = 'U.star DESC,U.rank DESC,U.sort DES';
                    break;
            }
            $query->order('create_time DESC,'.$order);
        }

        return $query->where('ProductGroup.is_del',0);
    }

    /**
     * 展示动作的执行结果
     *
     * 本函数用于返回一个表示展示状态的数组，包含多个状态标识和当前时间。
     * 这些状态标识用于表示展示是否应该显示、动作是否成功、产品状态等。
     * 返回的数组包含以下键值对：
     * - is_show: 表示展示是否应该显示，值为1表示应该显示。
     * - action_status: 表示动作的状态，值为1表示动作执行成功。
     * - product_status: 表示产品的状态，值为1表示产品处于有效状态。
     * - status: 通用状态标识，值为1表示一切正常。
     * - end_time: 表示当前时间，以Unix时间戳形式表示，用于记录动作的结束时间。
     *
     * @return array 包含状态信息的数组。
     */
    public function actionShow()
    {
        // 返回包含状态信息的数组
        return [
            'is_show' => 1,
            'action_status' => 1,
            'product_status' => 1,
            'status' => 1,
            'end_time' => time(),
        ];
    }

    /**
     * 获取展示状态为正常且可购买的产品分类路径列表
     *
     * 本函数通过查询产品组（ProductGroup）关联的产品（StoreProduct）及其分类（StoreCategory）
     * 来获取展示状态、动作状态及产品状态都为正常的产品的分类路径。
     * 使用了别名简化查询语句，通过连接（join）产品组、产品和分类表，筛选出符合条件的产品，
     * 并按产品ID分组，最后返回每个产品的分类路径列表。
     *
     * @return array 返回符合条件的产品的分类路径列表
     */
    public function category()
    {
        // 使用别名简化表名，并连接产品组、产品和分类表
        $query = ProductGroup::alias('G')->join('StoreProduct P','G.product_id = P.product_id')
            ->join('StoreCategory C','P.cate_id = C.store_category_id');

        // 筛选展示状态、动作状态及产品状态都为正常的产品
        $query->where('G.is_show',1)->where('G.action_status',1)->where('G.product_status',1);

        // 按产品ID分组，以确保每个产品只返回一个分类路径
        $query->group('G.product_id');

        // 返回查询结果中产品的分类路径列表
        return $query->column('path');
    }

    /**
     * 检查并更新过期活动的状态
     * 该方法用于定期检查所有活动的结束时间，如果活动已经结束，则将其状态设置为失效。
     * 同时，此方法还会更新关联的SPU的状态，将这些SPU的状态设置为不可用。
     *
     * @return void
     */
    public function valActiveStatus()
    {
        // 查询已结束且状态为有效的活动的ID
        $query = $this->getModel()::getDB()->whereTime('end_time','<=',time())->where('action_status',1);
        $id = $query->column($this->getPk());

        // 如果查询到已结束的活动ID
        if($id) {
            // 更新这些活动的状态为失效
            $this->getModel()::getDB()->where($this->getPk(),'in',$id)->update(['action_status' => -1]);

            // 准备更新关联SPU的状态
            $where = [
                'product_type' => 4,
                'activity_ids' => $id
            ];
            // 更新所有关联到这些活动的SPU的状态为不可用
            app()->make(SpuRepository::class)->getSearch($where)->update(['status' => 0]);
        }
    }


    /**
     * 软删除商户的所有商品
     * @param $merId
     * @author Qinii
     * @day 5/15/21
     */
    public function clearProduct($merId)
    {
        $this->getModel()::getDb()->where('mer_id', $merId)->update(['is_del' => 1]);
    }
}
