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
use app\common\model\store\product\ProductAssist;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\merchant\MerchantRepository;

class ProductAssistDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductAssist::class;
    }

    /**
     * 商品查询条件
     * @param array $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/16
     */
    public function search(array $where)
    {
        $query = ProductAssist::hasWhere('product',function($query)use($where){
            $query->when(isset($where['product_show']) && $where['product_show'] !== '',function($query)use($where){
                    $query->where('is_del',0)->where('mer_status',1)->where('product_type',3);
                })
                ->where('status',1)
                ->order('sort DESC,create_time DESC')
            ;
        });
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('ProductAssist.mer_id', $region);
        }
        $query->join('StoreSpu U', 'Product.product_id = U.product_id')->where('U.product_type', 3);
        $query->when(isset($where['product_assist_id']) && $where['product_assist_id'] !== '',function($query)use($where){
                $query->where('product_assist_id',$where['product_assist_id']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                $query->whereLike('ProductAssist.store_name|ProductAssist.product_id',"%{$where['keyword']}%");
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                if(is_array($where['mer_id'])) {
                    $query->whereIn('ProductAssist.mer_id', $where['mer_id']);
                } else {
                    $query->where('ProductAssist.mer_id',$where['mer_id']);
                }
            })
            ->when(isset($where['type']) && $where['type'] !== '',function($query)use($where){
                switch ($where['type']){
                    case 0: //未开始
                        $query->whereTime('start_time','>',time());
                        break;
                    case 1: //进行中
                        $query->whereTime('start_time','<=',time())->whereTime('end_time','>',time())
                        ->where('ProductAssist.product_status',1)->where('ProductAssist.status',1)->where('ProductAssist.is_show',1);
                        break;
                    case 2: //已结束
                        $query->where(function($query){
                            $query->where('action_status',-1)->whereOr('end_time','<= TIME',time());
                        });
                        break;
                }
            })
            ->when(isset($where['status']) && $where['status'] !== '',function($query)use($where){
                $query->where('ProductAssist.status',$where['status']);
            })
            ->when(isset($where['is_show']) && $where['is_show'] !== '',function($query)use($where){
                $query->where('ProductAssist.is_show',$where['is_show']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !== '',function($query)use($where){
                $query->where('ProductAssist.is_del',$where['is_del']);
            })
            ->when(isset($where['mer_name']) && $where['mer_name'] !== '',function($query)use($where){
                $make = app()->make(MerchantRepository::class);
                $mer_id = $make->search(['keyword' => $where['mer_name']])->column('mer_id');
                $query->whereIn('ProductAssist.mer_id',$mer_id);
            })
            ->when(isset($where['product_status']) && $where['product_status'] !== '',function($query)use($where){
                if($where['product_status'] == -1){
                    $query->where('ProductAssist.product_status','in',[-1,-2]);
                }else{
                    $query->where('ProductAssist.product_status',$where['product_status']);
                }
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '',function($query)use($where){
                $make = app()->make(MerchantRepository::class);
                $mer_id = $make->search(['is_trader' => $where['is_trader']])->column('mer_id');
                $query->whereIn('ProductAssist.mer_id',$mer_id);
            })
            ->when(isset($where['us_status']) && $where['us_status'] !== '',function($query)use($where){
                if($where['us_status'] == 0) {
                    $query->where('ProductAssist.is_show',0)->where('ProductAssist.status',1)->where('ProductAssist.product_status',1);
                }
                if($where['us_status'] == 1) {
                    $query->where('ProductAssist.is_show',1)->where('ProductAssist.status',1)->where('ProductAssist.product_status',1);
                }
                if($where['us_status'] == -1) {
                    $query->where(function($query){
                        $query->where('ProductAssist.status',0)->whereOr('ProductAssist.product_status','<>',1);
                    });
                }
            })
            ->when(isset($where['mer_labels']) && $where['mer_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.mer_labels', "%,{$where['mer_labels']},%");
            })
            ->when(isset($where['sys_labels']) && $where['sys_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.sys_labels', "%,{$where['sys_labels']},%");
            })
            ->when(isset($where['star']),function($query)use($where){
                $query->when($where['star'] !== '', function ($query) use ($where) {
                    $query->where('U.star', $where['star']);
                });
                $query->order('create_time DESC,U.star DESC,U.rank DESC');
            });
        $query->where('ProductAssist.is_del',0);
        return $query;
    }

    /**
     *  移动端展示 条件
     * @return array
     * @author Qinii
     * @day 2020-10-19
     */
    public function assistShow()
    {
        return [
            'product_show' => 1,
            'product_status' => 1,
            'status' => 1,
            'is_show' => 1,
            'type' => 1,
            'is_del' => 0
        ];
    }

    /**
     * 检查并更新过期活动的状态
     * 该方法用于定期检查所有活动的结束时间，如果活动已经结束，则将其状态设置为失效（-1）。
     * 同时，它也会更新关联的SPU（Special Product Unit）的状态，将这些SPU的活动状态设置为失效。
     * 这样做的目的是为了维护数据库中活动和SPU状态的准确性，确保只有当前有效的活动和SPU被展示或参与。
     */
    public function valActiveStatus()
    {
        // 根据当前时间，查询所有已经结束的活动，这些活动的状态为1（进行中）
        $query = $this->getModel()::getDB()->whereTime('end_time','<=',time())->where('action_status',1);
        // 获取这些活动的ID
        $id = $query->column($this->getPk());

        // 如果查询到了已结束的活动ID
        if($id) {
            // 更新这些活动的状态为-1（失效）
            $this->getModel()::getDB()->where($this->getPk(),'in',$id)->update(['action_status' => -1]);
            // 准备更新SPU状态的条件，特定类型的SPU且关联的活动ID在已结束的活动中
            $where = [
                'product_type' => 3,
                'activity_ids' => $id
            ];
            // 更新所有符合条件的SPU的状态为0（失效）
            app()->make(SpuRepository::class)->getSearch($where)->update(['status' => 0]);
        }
    }


    /**
     *  软删除商户的所有商品
     * @param $merId
     * @author Qinii
     * @day 5/15/21
     */
    public function clearProduct($merId)
    {
        $this->getModel()::getDb()->where('mer_id', $merId)->update(['is_del' => 1]);
    }
}

